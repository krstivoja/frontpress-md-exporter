<?php

declare(strict_types=1);

namespace FrontPressMdExp\Settings;

use FrontPressMdExp\Acf\Schema as AcfSchema;
use FrontPressMdExp\Plugin;

final class Mapping
{
    public static function defaults(): array
    {
        $postTypes  = [];
        $taxonomies = [];

        foreach (get_post_types(['public' => true], 'objects') as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }
            $folder = match ($pt->name) {
                'post'  => 'blog',
                'page'  => 'pages',
                default => $pt->name,
            };
            $postTypes[$pt->name] = [
                'include'    => true,
                'folder'     => $folder,
                'body_mode'  => 'markdown',
                'label'      => $pt->labels->name ?? $pt->name,
            ];
        }

        foreach (get_taxonomies(['public' => true], 'objects') as $tx) {
            $key = match ($tx->name) {
                'category' => 'categories',
                'post_tag' => 'tags',
                default    => $tx->name,
            };
            $taxonomies[$tx->name] = [
                'include' => true,
                'key'     => $key,
                'label'   => $tx->labels->name ?? $tx->name,
            ];
        }

        $acf = [];
        foreach (AcfSchema::inventory()['fields'] as $f) {
            $acf[$f['name']] = [
                'include' => true,
                'target'  => $f['name'],
            ];
        }

        return [
            'post_types' => $postTypes,
            'taxonomies' => $taxonomies,
            'meta'       => [],
            'acf'        => $acf,
            'include_unmapped_meta' => false,
        ];
    }

    public static function get(): array
    {
        $stored = get_option(Plugin::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return self::merge(self::defaults(), $stored);
    }

    public static function save(array $incoming): array
    {
        $merged = self::merge(self::defaults(), $incoming);
        update_option(Plugin::OPTION_KEY, $merged, false);
        return $merged;
    }

    private static function merge(array $defaults, array $incoming): array
    {
        $out = $defaults;

        foreach (['post_types', 'taxonomies'] as $section) {
            if (empty($incoming[$section]) || !is_array($incoming[$section])) {
                continue;
            }
            foreach ($incoming[$section] as $key => $cfg) {
                if (!isset($out[$section][$key]) || !is_array($cfg)) {
                    continue;
                }
                $out[$section][$key] = array_merge($out[$section][$key], $cfg);
            }
        }

        if (isset($incoming['acf']) && is_array($incoming['acf'])) {
            foreach ($incoming['acf'] as $name => $cfg) {
                if (!is_string($name) || !is_array($cfg)) {
                    continue;
                }
                $out['acf'][$name] = [
                    'include' => !empty($cfg['include']),
                    'target'  => sanitize_key((string) ($cfg['target'] ?? $name)),
                ];
            }
        }

        if (!empty($incoming['meta']) && is_array($incoming['meta'])) {
            $clean = [];
            foreach ($incoming['meta'] as $row) {
                if (!is_array($row) || empty($row['source'])) {
                    continue;
                }
                $clean[] = [
                    'source' => sanitize_key((string) $row['source']),
                    'target' => sanitize_key((string) ($row['target'] ?? $row['source'])),
                ];
            }
            $out['meta'] = $clean;
        }

        $out['include_unmapped_meta'] = !empty($incoming['include_unmapped_meta']);

        return $out;
    }
}
