<?php

declare(strict_types=1);

namespace Vusys\CmTools\Reflow;

use Vusys\CmTools\Support\DocumentParser;

/**
 * Transforms flowing CommonMark into fixed-width CommonMark: prose paragraphs (and other
 * text-bearing blocks — list items, blockquotes, footnotes) are re-wrapped so no line exceeds
 * the given column width, while structural content that shouldn't be reflowed — code blocks,
 * tables, headings — is left as one logical unit.
 */
final class Wrapper
{
    public function wrap(string $markdown, int $width): string
    {
        $document = DocumentParser::parse(InlineRenderer::withoutSentinels($markdown));

        return new BlockRenderer($width)->render($document);
    }
}
