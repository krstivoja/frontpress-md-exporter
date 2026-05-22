<?php

declare(strict_types=1);

namespace FrontPressMdExp\Export;

use League\HTMLToMarkdown\HtmlConverter;

final class BodyConverter
{
    private ?HtmlConverter $converter = null;

    /**
     * Convert pre-rendered HTML to either Markdown or pass-through HTML.
     */
    public function convert(string $html, string $mode): string
    {
        if ($mode === 'html') {
            return trim($html);
        }
        return trim($this->converter()->convert($html));
    }

    private function converter(): HtmlConverter
    {
        if ($this->converter === null) {
            $this->converter = new HtmlConverter([
                'strip_tags'      => false,
                'header_style'    => 'atx',
                'hard_break'      => false,
                'use_autolinks'   => true,
                'italic_style'    => '_',
                'bold_style'      => '**',
                'list_item_style' => '-',
            ]);
        }
        return $this->converter;
    }
}
