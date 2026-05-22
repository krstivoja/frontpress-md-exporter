<?php

declare(strict_types=1);

namespace FrontPressMdExp\Acf;

/**
 * Reads ACF field group definitions and reduces them to a flat schema:
 *
 *   [
 *     'available' => bool,
 *     'fields'    => [
 *       [
 *         'name'        => string,           // ACF field_name
 *         'label'       => string,
 *         'type'        => string,           // text, image, repeater, group, ...
 *         'group_title' => string,
 *         'post_types'  => string[],         // resolved from location rules
 *       ],
 *       ...
 *     ],
 *   ]
 */
final class Schema
{
    public static function isAvailable(): bool
    {
        return function_exists('acf_get_field_groups')
            && function_exists('acf_get_fields')
            && function_exists('get_field');
    }

    public static function inventory(): array
    {
        if (!self::isAvailable()) {
            return ['available' => false, 'fields' => []];
        }

        $allPublic = array_keys(get_post_types(['public' => true]));
        $rows      = [];
        $groups    = acf_get_field_groups();

        foreach ($groups as $group) {
            $postTypes = self::postTypesForGroup($group, $allPublic);
            $fields    = acf_get_fields($group['key']) ?: [];

            foreach ($fields as $field) {
                if (!isset($field['name']) || $field['name'] === '') {
                    continue;
                }
                $rows[] = [
                    'name'        => (string) $field['name'],
                    'label'       => (string) ($field['label'] ?? $field['name']),
                    'type'        => (string) ($field['type'] ?? 'text'),
                    'group_title' => (string) ($group['title'] ?? ''),
                    'post_types'  => $postTypes,
                ];
            }
        }

        return ['available' => true, 'fields' => $rows];
    }

    /**
     * Return the field definitions (including sub-fields) for a given post,
     * across every ACF group whose location rules bind it to this post's type.
     *
     * @return array<int, array<string, mixed>>  ACF field definitions.
     */
    public static function fieldsForPost(\WP_Post $post): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $allPublic = array_keys(get_post_types(['public' => true]));
        $out       = [];
        foreach (acf_get_field_groups() as $group) {
            $postTypes = self::postTypesForGroup($group, $allPublic);
            if ($postTypes !== ['*'] && !in_array($post->post_type, $postTypes, true)) {
                continue;
            }
            foreach ((acf_get_fields($group['key']) ?: []) as $f) {
                $out[] = $f;
            }
        }
        return $out;
    }

    /**
     * Resolve location rules to a list of post types this group applies to.
     * Only `post_type ==` and `post_type !=` are honored. Any other rule is
     * treated as "applies to all" so we don't drop fields silently.
     *
     * @return string[] post type slugs, or ['*'] meaning "applies to all".
     */
    private static function postTypesForGroup(array $group, array $allPublic): array
    {
        $location = $group['location'] ?? [];
        if (!is_array($location) || $location === []) {
            return ['*'];
        }

        $matched      = [];
        $sawPostTypeRule = false;

        foreach ($location as $orGroup) {
            if (!is_array($orGroup)) {
                continue;
            }
            $allow = $allPublic;
            foreach ($orGroup as $rule) {
                if (!is_array($rule) || ($rule['param'] ?? '') !== 'post_type') {
                    continue;
                }
                $sawPostTypeRule = true;
                $value = (string) ($rule['value'] ?? '');
                if (($rule['operator'] ?? '==') === '==') {
                    $allow = array_intersect($allow, [$value]);
                } else {
                    $allow = array_diff($allow, [$value]);
                }
            }
            foreach ($allow as $pt) {
                $matched[$pt] = true;
            }
        }

        if (!$sawPostTypeRule) {
            return ['*'];
        }
        return array_values(array_keys($matched));
    }
}
