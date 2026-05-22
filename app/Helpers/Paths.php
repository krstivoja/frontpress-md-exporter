<?php

declare(strict_types=1);

namespace FrontPressMdExp\Helpers;

final class Paths
{
    public static function workRoot(): string
    {
        $upload = wp_upload_dir();
        $root   = trailingslashit($upload['basedir']) . 'frontpress-mdexp';
        if (!is_dir($root)) {
            wp_mkdir_p($root);
        }
        return $root;
    }

    public static function runDir(string $runId): string
    {
        return self::workRoot() . '/' . $runId;
    }

    public static function ensure(string $dir): string
    {
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    public static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                self::rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
