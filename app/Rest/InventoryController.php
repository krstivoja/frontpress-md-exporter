<?php

declare(strict_types=1);

namespace FrontPressMdExp\Rest;

use FrontPressMdExp\Acf\Schema as AcfSchema;
use FrontPressMdExp\Plugin;
use FrontPressMdExp\Settings\Mapping;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class InventoryController
{
    public function register(): void
    {
        register_rest_route(Plugin::REST_NAMESPACE, '/inventory', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'inventory'],
            'permission_callback' => [Auth::class, 'canManage'],
        ]);
    }

    public function inventory(WP_REST_Request $req): WP_REST_Response
    {
        $defaults = Mapping::defaults();

        $postTypes = [];
        foreach (get_post_types(['public' => true], 'objects') as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }
            $count = wp_count_posts($pt->name);
            $sum   = 0;
            foreach (['publish', 'draft', 'private', 'pending', 'future'] as $st) {
                $sum += isset($count->$st) ? (int) $count->$st : 0;
            }
            $postTypes[] = [
                'name'        => $pt->name,
                'label'       => $pt->labels->name ?? $pt->name,
                'count'       => $sum,
                'taxonomies'  => array_values(get_object_taxonomies($pt->name)),
                'default'     => $defaults['post_types'][$pt->name] ?? null,
            ];
        }

        $taxonomies = [];
        foreach (get_taxonomies(['public' => true], 'objects') as $tx) {
            $taxonomies[] = [
                'name'    => $tx->name,
                'label'   => $tx->labels->name ?? $tx->name,
                'objects' => array_values($tx->object_type),
                'default' => $defaults['taxonomies'][$tx->name] ?? null,
            ];
        }

        $metaKeys = $this->sampleMetaKeys();
        $acf      = AcfSchema::inventory();

        return new WP_REST_Response([
            'post_types' => $postTypes,
            'taxonomies' => $taxonomies,
            'meta_keys'  => $metaKeys,
            'acf'        => $acf,
        ]);
    }

    /**
     * Sample meta keys across recent posts (limited to keep it cheap).
     * Excludes underscore-prefixed (private) keys.
     */
    private function sampleMetaKeys(): array
    {
        global $wpdb;
        $rows = $wpdb->get_col(
            "SELECT DISTINCT meta_key FROM {$wpdb->postmeta}
             WHERE meta_key NOT LIKE '\\_%' ESCAPE '\\\\'
             ORDER BY meta_key ASC
             LIMIT 200"
        );
        return is_array($rows) ? array_values($rows) : [];
    }
}
