<?php

declare(strict_types=1);

namespace FrontPressMdExp\Export;

use ZipArchive;

final class ZipBuilder
{
    /**
     * Zip the contents of $sourceDir into $zipPath.
     * Stored paths are $prefix . <path-relative-to-$sourceDir>.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function build(string $sourceDir, string $zipPath, string $prefix = ''): array
    {
        if (!class_exists(ZipArchive::class)) {
            return ['ok' => false, 'error' => 'ZipArchive extension is not available.'];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'error' => 'Could not create zip at ' . $zipPath];
        }

        $sourceDir = rtrim($sourceDir, '/');
        $base      = realpath($sourceDir) ?: $sourceDir;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $abs = $file->getPathname();
            $rel = ltrim(substr($abs, strlen($base)), '/\\');
            $zip->addFile($abs, $prefix . $rel);
        }

        $zip->close();
        return ['ok' => true];
    }
}
