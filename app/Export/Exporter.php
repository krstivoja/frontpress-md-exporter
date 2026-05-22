<?php

declare(strict_types=1);

namespace FrontPressMdExp\Export;

use FrontPressMdExp\Helpers\Paths;
use FrontPressMdExp\Settings\Mapping;

/**
 * Owns the run directory and state. Each instance is bound to one run_id.
 *
 * State file layout (run-dir/state.json):
 *   {
 *     "run_id": "...",
 *     "settings": {...},
 *     "post_ids": [int, ...],         // ordered queue
 *     "cursor": int,                  // next index to process
 *     "used_slugs": { folder: { slug:true, ... } },
 *     "terms_seen": { wp_tax: [slug, ...] },
 *     "download_token": string|null,
 *     "zip_filename": string|null
 *   }
 */
final class Exporter
{
    private string $runDir;
    private string $sitePrefix = 'site/';

    public function __construct(private string $runId)
    {
        $this->runDir = Paths::runDir($runId);
    }

    public static function start(): array
    {
        $settings = Mapping::get();

        $runId = wp_generate_password(16, false, false);
        $self  = new self($runId);
        Paths::ensure($self->runDir);
        Paths::ensure($self->runDir . '/site/content');

        $postIds = $self->collectPostIds($settings);

        $state = [
            'run_id'         => $runId,
            'settings'       => $settings,
            'post_ids'       => $postIds,
            'cursor'         => 0,
            'used_slugs'     => [],
            'terms_seen'     => [],
            'download_token' => null,
            'zip_filename'   => null,
            'started_at'     => time(),
        ];
        $self->writeState($state);

        return [
            'run_id' => $runId,
            'total'  => count($postIds),
        ];
    }

    public function tick(int $batch = 20): array
    {
        $state = $this->readState();
        if ($state === null) {
            return ['error' => 'unknown_run', 'message' => 'Run not found.'];
        }

        $ids   = $state['post_ids'] ?? [];
        $start = (int) ($state['cursor'] ?? 0);
        $end   = min(count($ids), $start + max(1, $batch));

        $body      = new BodyConverter();
        $media     = new MediaCollector();
        $formatter = new PostFormatter($body, $media);

        $usedSlugs = $state['used_slugs'] ?? [];
        $termsSeen = $state['terms_seen'] ?? [];

        for ($i = $start; $i < $end; $i++) {
            $postId = (int) $ids[$i];
            $post   = get_post($postId);
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $result = $formatter->format($post, $state['settings'], $usedSlugs);

            // Write the .md
            $mdAbs = $this->runDir . '/' . $result['md_rel'];
            Paths::ensure(dirname($mdAbs));
            file_put_contents($mdAbs, $result['md_contents']);

            // Copy media
            foreach ($result['files'] as $file) {
                $destAbs = $this->runDir . '/' . $file['dest_rel'];
                Paths::ensure(dirname($destAbs));
                @copy($file['src'], $destAbs);
            }

            // Track terms
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

        $state['cursor']     = $end;
        $state['used_slugs'] = $usedSlugs;
        $state['terms_seen'] = $termsSeen;
        $this->writeState($state);

        return [
            'processed' => $end,
            'total'     => count($ids),
            'done'      => $end >= count($ids),
        ];
    }

    public function finalize(): array
    {
        $state = $this->readState();
        if ($state === null) {
            return ['error' => 'unknown_run'];
        }
        if ((int) $state['cursor'] < count($state['post_ids'])) {
            return ['error' => 'not_finished'];
        }

        // Write site/config.json
        $configPath = $this->runDir . '/site/config.json';
        Paths::ensure(dirname($configPath));
        $config = ConfigBuilder::build($state['settings'], $state['terms_seen'] ?? []);
        file_put_contents($configPath, wp_json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Zip site/ tree
        $zipName = 'frontpress-mdexp-export-' . gmdate('Ymd-His') . '.zip';
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

        // Cleanup after successful send
        Paths::rrmdir($this->runDir);
        return true;
    }

    public function runId(): string
    {
        return $this->runId;
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
        file_put_contents(
            $this->runDir . '/state.json',
            wp_json_encode($state, JSON_UNESCAPED_SLASHES)
        );
    }

    private function collectPostIds(array $settings): array
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
}
