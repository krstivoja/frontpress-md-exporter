<?php

declare(strict_types=1);

namespace FrontPressMdExp\Export;

use Symfony\Component\Yaml\Yaml;

final class FrontMatter
{
    public static function encode(array $data): string
    {
        $data = self::stripEmpty($data);
        if ($data === []) {
            return "---\n---\n";
        }
        $yaml = Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        return "---\n" . $yaml . "---\n";
    }

    private static function stripEmpty(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }
}
