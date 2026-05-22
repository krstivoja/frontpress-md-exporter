<?php

declare(strict_types=1);

namespace FrontPressMdExp\Export;

use FrontPressMdExp\Acf\Reader as AcfReader;
use FrontPressMdExp\Acf\Schema as AcfSchema;
use FrontPressMdExp\Helpers\Slug;

final class PostFormatter
{
    public function __construct(
        private BodyConverter $body,
        private MediaCollector $media
    ) {
    }

    /**
     * @return array{
     *   md_rel: string,
     *   md_contents: string,
     *   files: array<int, array{src:string, dest_rel:string, public_url:string}>,
     *   taxonomy_terms: array<string, array<int, string>>,
     *   folder: string,
     *   slug: string
     * }
     */
    public function format(\WP_Post $post, array $settings, array &$usedSlugs): array
    {
        $ptCfg  = $settings['post_types'][$post->post_type] ?? null;
        $folder = $this->sanitizeFolder($ptCfg['folder'] ?? $post->post_type);
        $mode   = $ptCfg['body_mode'] ?? 'markdown';

        if (!isset($usedSlugs[$folder]) || !is_array($usedSlugs[$folder])) {
            $usedSlugs[$folder] = [];
        }
        $candidate = Slug::clean($post->post_name !== '' ? $post->post_name : $post->post_title, 'post-' . $post->ID);
        $slug      = Slug::unique($candidate, $usedSlugs[$folder]);

        // Reset media collector for this post.
        $this->media->reset($folder, $slug);

        // Featured image first so it owns its basename slot.
        $this->media->ingestFeatured($post);

        // Render HTML through filters first so blocks/shortcodes expand.
        $rendered = (string) apply_filters('the_content', $post->post_content);
        $rendered = is_string($rendered) ? $rendered : '';

        // Queue inline media from the rendered HTML.
        $this->media->ingestHtml($rendered);

        // ACF (also queues media via the collector). Done before plan() snapshot.
        $acfData = AcfSchema::isAvailable()
            ? (new AcfReader($this->media))->read($post, $settings['acf'] ?? [])
            : [];

        // Snapshot of media plan.
        $plan = $this->media->plan();

        // Body output: rewrite URLs in the HTML, then convert (or pass through).
        $rewrote = $this->media->rewriteHtml($rendered);
        $body    = $this->body->convert($rewrote, $mode);

        // Front matter
        $taxonomyTerms = [];
        $tax           = [];
        foreach ($settings['taxonomies'] ?? [] as $wpTax => $cfg) {
            if (empty($cfg['include'])) {
                continue;
            }
            $terms = wp_get_post_terms($post->ID, $wpTax, ['fields' => 'all']);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }
            $slugs = [];
            foreach ($terms as $t) {
                $slugs[] = $t->slug;
            }
            $tax[$cfg['key']]      = $slugs;
            $taxonomyTerms[$wpTax] = $slugs;
        }

        $frontMatter = [
            'title' => html_entity_decode($post->post_title, ENT_QUOTES | ENT_HTML5),
            'date'  => $this->formatDate($post),
        ];
        if ($post->post_status !== 'publish') {
            $frontMatter['draft'] = true;
        }
        if ($post->post_excerpt !== '') {
            $frontMatter['excerpt'] = wp_strip_all_tags($post->post_excerpt);
        }
        if (!empty($plan['featured_url'])) {
            $frontMatter['image'] = $plan['featured_url'];
        }
        foreach ($tax as $k => $v) {
            $frontMatter[$k] = $v;
        }

        // Mapped meta (raw post_meta — runs alongside ACF, useful for non-ACF keys).
        foreach ($settings['meta'] ?? [] as $row) {
            $src = $row['source'] ?? '';
            $dst = $row['target'] ?? $src;
            if ($src === '') {
                continue;
            }
            $val = get_post_meta($post->ID, $src, true);
            if ($val === '' || $val === null) {
                continue;
            }
            $frontMatter[$dst] = $this->coerceMeta($val);
        }

        // ACF (overlays meta with structured values when both are mapped).
        foreach ($acfData as $k => $v) {
            $frontMatter[$k] = $v;
        }

        if (!empty($settings['include_unmapped_meta'])) {
            $mapped = array_flip(array_map(static fn($r) => $r['source'] ?? '', $settings['meta'] ?? []));
            foreach ((array) get_post_meta($post->ID) as $k => $v) {
                if (str_starts_with((string) $k, '_') || isset($mapped[$k]) || isset($frontMatter[$k])) {
                    continue;
                }
                $first = is_array($v) ? reset($v) : $v;
                if ($first === '' || $first === null) {
                    continue;
                }
                $frontMatter[$k] = $this->coerceMeta(maybe_unserialize($first));
            }
        }

        $contents = FrontMatter::encode($frontMatter) . "\n" . $body . "\n";

        return [
            'md_rel'         => 'site/content/' . $folder . '/' . $slug . '.md',
            'md_contents'    => $contents,
            'files'          => $plan['files'],
            'taxonomy_terms' => $taxonomyTerms,
            'folder'         => $folder,
            'slug'           => $slug,
        ];
    }

    private function formatDate(\WP_Post $post): string
    {
        $gmt = $post->post_date_gmt !== '0000-00-00 00:00:00' ? $post->post_date_gmt : $post->post_date;
        $ts  = strtotime($gmt) ?: time();
        return gmdate('Y-m-d', $ts);
    }

    private function coerceMeta(mixed $v): mixed
    {
        if (is_string($v) || is_int($v) || is_float($v) || is_bool($v) || is_array($v) || $v === null) {
            return $v;
        }
        return (string) $v;
    }

    /**
     * Sanitize folder path to prevent directory traversal
     */
    private function sanitizeFolder(string $folder): string
    {
        // Remove path separators and traversal attempts
        $folder = str_replace(['/', '\\', '..', '.'], '', $folder);
        // Only allow alphanumeric, dash, underscore
        $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder);
        return $folder !== '' ? $folder : 'content';
    }
}
