<?php

declare(strict_types=1);

namespace FrontPressMdExp\Export;

final class ConfigBuilder
{
    /**
     * Build site/config.json content.
     *
     * @param array $settings  Mapping settings (post_types + taxonomies).
     * @param array $termsSeen [wp_taxonomy_slug => [term_slug, ...]] across all exported posts.
     */
    public static function build(array $settings, array $termsSeen): array
    {
        $taxonomies = [];
        foreach ($settings['taxonomies'] ?? [] as $wpTax => $cfg) {
            if (empty($cfg['include'])) {
                continue;
            }
            $key       = $cfg['key'];
            $postTypes = self::postTypesUsing($wpTax, $settings['post_types'] ?? []);
            if ($postTypes === []) {
                continue;
            }
            $items = array_values(array_unique($termsSeen[$wpTax] ?? []));
            sort($items);

            $taxonomies[$key] = [
                'label'      => $cfg['label'] ?? ucfirst($key),
                'post_types' => $postTypes,
                'fields'     => [[
                    'name'     => $key,
                    'type'     => 'array',
                    'widget'   => 'checkbox',
                    'multiple' => true,
                    'items'    => $items,
                    'hidden'   => false,
                ]],
            ];
        }

        return [
            'site' => [
                'name' => get_bloginfo('name'),
                'base' => '/',
            ],
            'taxonomies'   => (object) $taxonomies,
            'active_theme' => 'blank',
            'uploads'      => [
                'max_mb'     => 5,
                'max_width'  => 0,
                'max_height' => 0,
            ],
        ];
    }

    private static function postTypesUsing(string $wpTax, array $postTypeMapping): array
    {
        $folders = [];
        foreach ($postTypeMapping as $wpType => $cfg) {
            if (empty($cfg['include'])) {
                continue;
            }
            // Network mode passes a captured taxonomy list in `_taxonomies`
            // so we don't depend on the current blog's CPT registry.
            $taxes = $cfg['_taxonomies'] ?? get_object_taxonomies($wpType);
            if (in_array($wpTax, $taxes, true)) {
                $folders[] = $cfg['folder'] ?? $wpType;
            }
        }
        return array_values(array_unique($folders));
    }

    /**
     * Merge per-subsite settings/terms into one config.json.
     *
     * @param array<int, array{settings:array, terms_seen:array}> $perSite
     */
    public static function buildNetwork(array $perSite): array
    {
        $taxonomies = [];

        foreach ($perSite as $row) {
            $settings  = $row['settings'] ?? [];
            $termsSeen = $row['terms_seen'] ?? [];

            foreach ($settings['taxonomies'] ?? [] as $wpTax => $cfg) {
                if (empty($cfg['include'])) {
                    continue;
                }
                $key       = $cfg['key'];
                $postTypes = self::postTypesUsing($wpTax, $settings['post_types'] ?? []);
                if ($postTypes === []) {
                    continue;
                }
                $items = array_values(array_unique($termsSeen[$wpTax] ?? []));

                if (!isset($taxonomies[$key])) {
                    $taxonomies[$key] = [
                        'label'      => $cfg['label'] ?? ucfirst($key),
                        'post_types' => [],
                        '_items'     => [],
                    ];
                }
                foreach ($postTypes as $pt) {
                    if (!in_array($pt, $taxonomies[$key]['post_types'], true)) {
                        $taxonomies[$key]['post_types'][] = $pt;
                    }
                }
                foreach ($items as $it) {
                    $taxonomies[$key]['_items'][$it] = true;
                }
            }
        }

        $finalTax = [];
        foreach ($taxonomies as $key => $row) {
            $items = array_keys($row['_items']);
            sort($items);
            $finalTax[$key] = [
                'label'      => $row['label'],
                'post_types' => $row['post_types'],
                'fields'     => [[
                    'name'     => $key,
                    'type'     => 'array',
                    'widget'   => 'checkbox',
                    'multiple' => true,
                    'items'    => $items,
                    'hidden'   => false,
                ]],
            ];
        }

        return [
            'site' => [
                'name' => get_network() ? get_network()->site_name : get_bloginfo('name'),
                'base' => '/',
            ],
            'taxonomies'   => (object) $finalTax,
            'active_theme' => 'blank',
            'uploads'      => [
                'max_mb'     => 5,
                'max_width'  => 0,
                'max_height' => 0,
            ],
        ];
    }
}
