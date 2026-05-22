<?php

declare(strict_types=1);

namespace FrontPressMdExp\Helpers;

final class Slug
{
    /**
     * Sanitize a slug, falling back to a generated stem when empty.
     */
    public static function clean(string $raw, string $fallback = 'item'): string
    {
        $slug = sanitize_title($raw);
        if ($slug === '') {
            $slug = sanitize_title($fallback);
        }
        return $slug !== '' ? $slug : 'item';
    }

    /**
     * Return a slug not already present in $taken; appends -2, -3, ... if needed.
     * Mutates $taken to record the chosen slug.
     */
    public static function unique(string $candidate, array &$taken): string
    {
        $slug = $candidate;
        $i    = 2;
        while (isset($taken[$slug])) {
            $slug = $candidate . '-' . $i++;
        }
        $taken[$slug] = true;
        return $slug;
    }

    /**
     * Sanitize a basename so it's safe to write to disk: strip directory parts,
     * collapse to ascii-friendly characters, dedupe within $taken (case-insensitive).
     */
    public static function uniqueBasename(string $raw, array &$taken): string
    {
        $base = basename($raw);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? 'file';
        $base = trim($base, '-.');
        if ($base === '') {
            $base = 'file';
        }

        $key = strtolower($base);
        if (!isset($taken[$key])) {
            $taken[$key] = true;
            return $base;
        }

        $dot      = strrpos($base, '.');
        $stem     = $dot === false ? $base : substr($base, 0, $dot);
        $ext      = $dot === false ? '' : substr($base, $dot);
        $i        = 2;
        do {
            $candidate = $stem . '-' . $i . $ext;
            $key       = strtolower($candidate);
            $i++;
        } while (isset($taken[$key]));
        $taken[$key] = true;
        return $candidate;
    }
}
