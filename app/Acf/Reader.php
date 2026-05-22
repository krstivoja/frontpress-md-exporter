<?php

declare(strict_types=1);

namespace FrontPressMdExp\Acf;

use FrontPressMdExp\Export\MediaCollector;

/**
 * Read ACF field values for a post and reduce them to plain PHP values
 * suitable for YAML front matter. Image/file/gallery values are routed
 * through MediaCollector so the underlying file is staged and the URL
 * is rewritten to /uploads/<folder>/<slug>/<basename>.
 */
final class Reader
{
    public function __construct(private MediaCollector $media)
    {
    }

    /**
     * @param array<string, array{include:bool, target:string}> $mapping  keyed by ACF field name
     * @return array<string, mixed>  values keyed by target front-matter key
     */
    public function read(\WP_Post $post, array $mapping): array
    {
        if (!Schema::isAvailable() || $mapping === []) {
            return [];
        }

        $out    = [];
        $fields = Schema::fieldsForPost($post);

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            $cfg  = $mapping[$name] ?? null;
            if ($cfg === null || empty($cfg['include'])) {
                continue;
            }
            $target = (string) ($cfg['target'] ?? $name);
            if ($target === '') {
                $target = $name;
            }

            $value = get_field($name, $post->ID, true);
            $coerced = $this->coerceField($field, $value);
            if ($coerced === null || $coerced === '' || $coerced === []) {
                continue;
            }
            $out[$target] = $coerced;
        }
        return $out;
    }

    /**
     * Recursively reduce an ACF field value to scalar/array form.
     */
    private function coerceField(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        switch ($type) {
            case 'image':
            case 'file':
                return $this->coerceMediaSingle($value);

            case 'gallery':
                if (!is_array($value)) {
                    return null;
                }
                $out = [];
                foreach ($value as $item) {
                    $url = $this->coerceMediaSingle($item);
                    if ($url !== null) {
                        $out[] = $url;
                    }
                }
                return $out;

            case 'post_object':
            case 'relationship':
                return $this->coerceRelation($value);

            case 'taxonomy':
                return $this->coerceTaxonomy($value, (string) ($field['taxonomy'] ?? ''));

            case 'user':
                return $this->coerceUser($value);

            case 'true_false':
                return (bool) $value;

            case 'date_picker':
            case 'date_time_picker':
            case 'time_picker':
                return is_string($value) ? $value : (string) $value;

            case 'number':
            case 'range':
                if (is_int($value) || is_float($value)) {
                    return $value;
                }
                if (is_numeric($value)) {
                    return strpos((string) $value, '.') === false ? (int) $value : (float) $value;
                }
                return null;

            case 'repeater':
                if (!is_array($value)) {
                    return null;
                }
                $sub = $field['sub_fields'] ?? [];
                $rows = [];
                foreach ($value as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $rows[] = $this->coerceRow($sub, $row);
                }
                return $rows;

            case 'group':
                if (!is_array($value)) {
                    return null;
                }
                $sub = $field['sub_fields'] ?? [];
                return $this->coerceRow($sub, $value);

            case 'flexible_content':
                if (!is_array($value)) {
                    return null;
                }
                $layouts = [];
                foreach ((array) ($field['layouts'] ?? []) as $layout) {
                    $layouts[(string) $layout['name']] = $layout['sub_fields'] ?? [];
                }
                $rows = [];
                foreach ($value as $row) {
                    if (!is_array($row) || empty($row['acf_fc_layout'])) {
                        continue;
                    }
                    $name = (string) $row['acf_fc_layout'];
                    $sub  = $layouts[$name] ?? [];
                    $row  = $this->coerceRow($sub, $row);
                    $row['_layout'] = $name;
                    $rows[] = $row;
                }
                return $rows;

            case 'wysiwyg':
                return is_string($value) ? wp_strip_all_tags($value) : null;

            // text, textarea, select, checkbox, radio, button_group, email, url, password,
            // color_picker, oembed, link, etc. — pass through with light coercion.
            default:
                return $this->coerceScalar($value);
        }
    }

    private function coerceRow(array $subFields, array $row): array
    {
        $out = [];
        foreach ($subFields as $sf) {
            $name = (string) ($sf['name'] ?? '');
            if ($name === '' || !array_key_exists($name, $row)) {
                continue;
            }
            $v = $this->coerceField($sf, $row[$name]);
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $out[$name] = $v;
        }
        return $out;
    }

    private function coerceMediaSingle(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return $this->media->ingestAttachment((int) $value);
        }
        if (is_array($value)) {
            if (isset($value['ID']) || isset($value['id'])) {
                return $this->media->ingestAttachment((int) ($value['ID'] ?? $value['id']));
            }
            if (isset($value['url']) && is_string($value['url'])) {
                return $this->media->ingestLocalUrl($value['url']);
            }
            return null;
        }
        if (is_string($value) && $value !== '') {
            return $this->media->ingestLocalUrl($value);
        }
        return null;
    }

    private function coerceRelation(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $items = is_array($value) ? $value : [$value];
        $out   = [];
        foreach ($items as $it) {
            $id = $it instanceof \WP_Post ? $it->ID : (int) $it;
            $p  = get_post($id);
            if ($p instanceof \WP_Post) {
                $out[] = $p->post_name !== '' ? $p->post_name : (string) $p->ID;
            }
        }
        return $out === [] ? null : $out;
    }

    private function coerceTaxonomy(mixed $value, string $taxonomy): ?array
    {
        if ($value === null || $value === '' || $taxonomy === '') {
            return null;
        }
        $items = is_array($value) ? $value : [$value];
        $out   = [];
        foreach ($items as $it) {
            $term = $it instanceof \WP_Term ? $it : get_term((int) $it, $taxonomy);
            if ($term instanceof \WP_Term) {
                $out[] = $term->slug;
            }
        }
        return $out === [] ? null : $out;
    }

    private function coerceUser(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $items = is_array($value) && (isset($value['ID']) || isset($value['user_email'])) ? [$value] : (is_array($value) ? $value : [$value]);
        $out   = [];
        foreach ($items as $it) {
            if ($it instanceof \WP_User) {
                $out[] = $it->user_login;
            } elseif (is_array($it) && isset($it['user_login'])) {
                $out[] = (string) $it['user_login'];
            } else {
                $u = get_userdata((int) $it);
                if ($u) {
                    $out[] = $u->user_login;
                }
            }
        }
        return $out === [] ? null : $out;
    }

    private function coerceScalar(mixed $value): mixed
    {
        if (is_array($value)) {
            // Select/checkbox/radio with multi-value or array return format.
            $flat = [];
            foreach ($value as $k => $v) {
                if (is_scalar($v)) {
                    $flat[] = $v;
                } elseif (is_array($v) && isset($v['value'])) {
                    $flat[] = $v['value'];
                }
            }
            return $flat === [] ? null : $flat;
        }
        return is_scalar($value) ? $value : null;
    }
}
