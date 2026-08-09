<?php

declare(strict_types=1);

namespace Vusys\CmTools\Reflow;

use Vusys\CmTools\Support\DocumentParser;

/**
 * The inverse of {@see Wrapper}: collapses fixed-width-wrapped prose back to one line per
 * logical block (paragraph, list item, blockquote, footnote), so a document previously wrapped
 * for a narrow terminal or diff-friendliness reads as normal "one sentence flows to one source
 * line" CommonMark again. Explicit hard breaks (trailing double-space / backslash) are still
 * honoured — unwrapping removes soft wrapping, not authored line breaks.
 */
final class Unwrapper
{
    public function unwrap(string $markdown): string
    {
        $document = DocumentParser::parse(InlineRenderer::withoutSentinels($markdown));

        return new BlockRenderer(null)->render($document);
    }
}
