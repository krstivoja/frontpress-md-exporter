<?php

declare(strict_types=1);

namespace FrontPressMdExp\Network;

use FrontPressMdExp\Export\BodyConverter;
use FrontPressMdExp\Export\ConfigBuilder;
use FrontPressMdExp\Export\MediaCollector;
use FrontPressMdExp\Export\PostFormatter;
use FrontPressMdExp\Export\ZipBuilder;
use FrontPressMdExp\Helpers\Paths;
use FrontPressMdExp\Helpers\Slug;
use FrontPressMdExp\Settings\Mapping;

/**
 * Multisite export: every subsite becomes a top-level folder under
 * site/content/<subsite-slug>/, with the user's normal post-type folders
 * (blog, pages, custom) nested inside. Per-post media land in the same
 * tree at site/content/<subsite-slug>/<folder>/<slug>/<basename>, and
 * URLs are written as /uploads/<subsite-slug>/<folder>/<slug>/<basename>.
 *
 * Implementation: we reuse the per-site PostFormatter/MediaCollector by
 * pre-prefixing each subsite's mapping["post_types"][*]["folder"] with
 * the subsite slug — no changes needed downstream.
 *
 * State file layout (run-dir/state.json):
 *   {
 *     "run_id": "...",
 *     "subsites": [
 *       { "id": int, "slug": str, "post_ids": [int, ...], "cursor": int,
 *         "settings_prefixed": {...}, "terms_seen": {wp_tax: [slug,...]},
 *         "used_slugs": {folder: {slug:true}} },
 *       ...
 *     ],
 *     "site_cursor": int,            // next subsite to process
 *     "total": int,                  // grand total posts across all subsites
 *     "processed": int,              // grand processed
 *     "download_token": str|null,
 *     "zip_filename": str|null
 *   }
 */
final class Exporter
{
    private string $runDir;

    public function __construct(private string $runId)
    {
        // Network run dirs live in the network's main-site uploads (so we
        // don't scatter zips across subsite upload roots).
        $this->runDir = self::networkWorkRoot() . '/' . $runId;
    }

    public static function start(array $subsiteIds): array
    {
        if (!is_multisite()) {
            return ['error' => 'not_multisite'];
        }

        $runId = wp_generate_password(16, false, false);
        $self  = new self($runId);
        Paths::ensure($self->runDir);

        $subsites = [];
        $total    = 0;
        $errors   = [];

        foreach ($subsiteIds as $id) {
            $id   = (int) $id;
            $site = get_site($id);
            if (!$site) {
                $errors[] = "Site ID {$id} not found";
                continue;
            }

            try {
                $switched = switch_to_blog($id);
                if (!$switched) {
                    $errors[] = "Failed to switch to site {$id}";
                    continue;
                }

                $slug              = self::subsiteSlug($site);
                $settings          = Mapping::get();
                $settingsPrefixed  = self::prefixFolders($settings, $slug);
                $postIds           = self::collectPostIds($settingsPrefixed);

                $subsites[] = [
                    'id'                => $id,
                    'slug'              => $slug,
                    'post_ids'          => $postIds,
                    'cursor'            => 0,
                    'settings_prefixed' => $settingsPrefixed,
                    'terms_seen'        => [],
                    'used_slugs'        => [],
                ];
                $total += count($postIds);
            } catch (\Throwable $e) {
                $errors[] = "Site {$id}: " . $e->getMessage();
            } finally {
                restore_current_blog();
            }
        }

        if (empty($subsites)) {
            return [
                'error' => 'no_valid_sites',
                'message' => 'No valid sites could be processed.',
                'details' => $errors,
            ];
        }

        $state = [
            'run_id'         => $runId,
            'subsites'       => $subsites,
            'site_cursor'    => 0,
            'total'          => $total,
            'processed'      => 0,
            'download_token' => null,
            'zip_filename'   => null,
            'started_at'     => time(),
            'errors'         => $errors,
        ];
        $self->writeState($state);

        return [
            'run_id'   => $runId,
            'total'    => $total,
            'subsites' => array_map(static fn($s) => [
                'id' => $s['id'], 'slug' => $s['slug'], 'count' => count($s['post_ids']),
            ], $subsites),
            'warnings' => !empty($errors) ? $errors : null,
        ];
    }

    public function tick(int $batch = 20): array
    {
        $state = $this->readState();
        if ($state === null) {
            return ['error' => 'unknown_run'];
        }

        $body      = new BodyConverter();
        $media     = new MediaCollector();
        $formatter = new PostFormatter($body, $media);

        $produced = 0;
        while ($produced < $batch && $state['site_cursor'] < count($state['subsites'])) {
            $idx     = $state['site_cursor'];
            $subsite = $state['subsites'][$idx];

            switch_to_blog($subsite['id']);

            $ids   = $subsite['post_ids'];
            $start = (int) $subsite['cursor'];
            $end   = min(count($ids), $start + ($batch - $produced));

            $usedSlugs = $subsite['used_slugs'] ?? [];
            $termsSeen = $subsite['terms_seen'] ?? [];

            for ($i = $start; $i < $end; $i++) {
                $postId = (int) $ids[$i];
                $post   = get_post($postId);
                if (!$post instanceof \WP_Post) {
                    continue;
                }
                $result = $formatter->format($post, $subsite['settings_prefixed'], $usedSlugs);
                $this->writePostArtifacts($result);
                foreach ($result['taxonomy_terms'] as $wpTax => $slugs) {
                    if (!isset($termsSeen[$wpTax])) {
                        $termsSeen[$wpTax] = [];
                    }
                    foreach ($slugs as $s) {
                        if (!in_array($s, $termsSeen[$wpTax], true)) {
                            $termsSeen[$wpTax][] = $s;
                        }
                    }
                }
            }

            restore_current_blog();

            $subsite['cursor']     = $end;
            $subsite['used_slugs'] = $usedSlugs;
            $subsite['terms_seen'] = $termsSeen;
            $state['subsites'][$idx] = $subsite;

            $produced += ($end - $start);
            $state['processed'] += ($end - $start);

            if ($end >= count($ids)) {
                $state['site_cursor']++;
            }
        }

        $this->writeState($state);

        return [
            'processed' => (int) $state['processed'],
            'total'     => (int) $state['total'],
            'done'      => $state['site_cursor'] >= count($state['subsites']),
        ];
    }

    public function finalize(): array
    {
        $state = $this->readState();
        if ($state === null) {
            return ['error' => 'unknown_run'];
        }
        if ($state['site_cursor'] < count($state['subsites'])) {
            return ['error' => 'not_finished'];
        }

        // Build one merged config.json
        $configPath = $this->runDir . '/site/config.json';
        Paths::ensure(dirname($configPath));

        $perSite = array_map(static fn($s) => [
            'settings'   => $s['settings_prefixed'],
            'terms_seen' => $s['terms_seen'] ?? [],
        ], $state['subsites']);

        $config = ConfigBuilder::buildNetwork($perSite);
        file_put_contents($configPath, wp_json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Zip site/ tree
        $zipName = 'frontpress-mdexp-network-export-' . gmdate('Ymd-His') . '.zip';
        $zipAbs  = $this->runDir . '/' . $zipName;
        $built   = ZipBuilder::build($this->runDir . '/site', $zipAbs, 'site/');
        if (empty($built['ok'])) {
            return ['error' => 'zip_failed', 'message' => $built['error'] ?? 'Unknown zip error.'];
        }

        $token = wp_generate_password(24, false, false);
        $state['download_token'] = $token;
        $state['zip_filename']   = $zipName;
        $this->writeState($state);

        return [
            'token'    => $token,
            'filename' => $zipName,
            'size'     => (int) filesize($zipAbs),
        ];
    }

    public function streamDownload(string $token): bool
    {
        $state = $this->readState();
        if ($state === null) {
            status_header(404);
            return false;
        }
        if (!hash_equals((string) ($state['download_token'] ?? ''), $token)) {
            status_header(403);
            return false;
        }
        $zipAbs = $this->runDir . '/' . ($state['zip_filename'] ?? '');
        if (!is_file($zipAbs)) {
            status_header(404);
            return false;
        }
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipAbs) . '"');
        header('Content-Length: ' . filesize($zipAbs));
        readfile($zipAbs);
        Paths::rrmdir($this->runDir);
        return true;
    }

    private function writePostArtifacts(array $result): void
    {
        $mdAbs = $this->runDir . '/' . $result['md_rel'];
        Paths::ensure(dirname($mdAbs));
        file_put_contents($mdAbs, $result['md_contents']);
        foreach ($result['files'] as $file) {
            $destAbs = $this->runDir . '/' . $file['dest_rel'];
            Paths::ensure(dirname($destAbs));
            @copy($file['src'], $destAbs);
        }
    }

    private function readState(): ?array
    {
        $file = $this->runDir . '/state.json';
        if (!is_file($file)) {
            return null;
        }
        $raw  = file_get_contents($file);
        $data = $raw === false ? null : json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function writeState(array $state): void
    {
        Paths::ensure($this->runDir);
        file_put_contents($this->runDir . '/state.json', wp_json_encode($state, JSON_UNESCAPED_SLASHES));
    }

    private static function prefixFolders(array $settings, string $prefix): array
    {
        foreach ($settings['post_types'] ?? [] as $name => $cfg) {
            $folder = (string) ($cfg['folder'] ?? $name);
            $settings['post_types'][$name]['folder']      = $prefix . '/' . ltrim($folder, '/');
            // Capture taxonomies now (we're inside switch_to_blog) so
            // ConfigBuilder doesn't depend on the active blog at finalize time.
            $settings['post_types'][$name]['_taxonomies'] = array_values(get_object_taxonomies($name));
        }
        return $settings;
    }

    private static function collectPostIds(array $settings): array
    {
        $types = [];
        foreach ($settings['post_types'] ?? [] as $name => $cfg) {
            if (!empty($cfg['include'])) {
                $types[] = $name;
            }
        }
        if ($types === []) {
            return [];
        }
        $q = new \WP_Query([
            'post_type'              => $types,
            'post_status'            => ['publish', 'draft', 'private', 'pending', 'future'],
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters'       => true,
        ]);
        return array_map('intval', $q->posts);
    }

    private static function subsiteSlug(\WP_Site $site): string
    {
        // Prefer the URL path segment (e.g. "marketing"); fall back to host's
        // first label; final fallback is `site-<id>`.
        $path = trim((string) $site->path, '/');
        if ($path !== '') {
            return Slug::clean($path, 'site-' . (int) $site->blog_id);
        }
        $host = (string) $site->domain;
        if ($host !== '') {
            $first = explode('.', $host)[0];
            return Slug::clean($first, 'site-' . (int) $site->blog_id);
        }
        return 'site-' . (int) $site->blog_id;
    }

    private static function networkWorkRoot(): string
    {
        // Use the main site's uploads dir so multiple subsites don't each get
        // their own copy of the network run.
        $main = function_exists('get_main_site_id') ? (int) get_main_site_id() : 1;
        switch_to_blog($main);
        $root = Paths::workRoot() . '/network';
        if (!is_dir($root)) {
            wp_mkdir_p($root);
        }
        restore_current_blog();
        return $root;
    }
}
