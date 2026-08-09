<?php

declare(strict_types=1);

namespace Vusys\CmTools\Reflow;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Splits a GitHub alert marker (`[!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`,
 * `[!CAUTION]`) off the front of a blockquote's opening paragraph, when it sits alone on
 * that paragraph's first line.
 *
 * GitHub (and several other renderers) special-case exactly this shape into a styled
 * callout, but only when the marker is alone on the blockquote's first physical line and
 * unescaped. Syntactically it is otherwise just the first line of an ordinary
 * soft-wrapped paragraph — CommonMark itself draws no distinction, and
 * {@see InlineRenderer} backslash-escapes a literal `[`/`]` in prose on general
 * principle — so left to the normal render path, a reflow can both merge the marker into
 * the following prose (wide enough width, or `unwrap`'s unlimited one) and escape its
 * brackets, turning a callout back into a plain quote with literal `\[!NOTE\]` text in
 * it. {@see BlockRenderer::renderParagraph()} uses {@see self::split()} to render the
 * marker as a bare, unescaped line of its own, every time — nothing here changes what
 * the document *means*, only how its first paragraph is line-packed.
 */
final class AlertMarker
{
    private const array TYPES = ['NOTE', 'TIP', 'IMPORTANT', 'WARNING', 'CAUTION'];

    /**
     * @return array{0: string, 1: list<Node>}|null the bare marker text, and the inline nodes
     *                                              making up the rest of the paragraph — null
     *                                              if the paragraph doesn't open with a marker
     */
    public static function split(Paragraph $paragraph): ?array
    {
        if (! $paragraph->parent() instanceof BlockQuote || $paragraph->previous() instanceof Node) {
            return null;
        }

        $marker = $paragraph->firstChild();

        if (! $marker instanceof Text || ! in_array($marker->getLiteral(), self::markerLiterals(), true)) {
            return null;
        }

        $break = $marker->next();

        // A marker with no body at all (the whole paragraph is just `[!NOTE]`) is still a
        // marker to keep unescaped — an empty callout is unusual, but it is not this class's
        // job to judge that, only to recognise the shape.
        if (! $break instanceof Node) {
            return [$marker->getLiteral(), []];
        }

        if (! $break instanceof Newline || $break->getType() !== Newline::SOFTBREAK) {
            return null;
        }

        $rest = [];

        for ($node = $break->next(); $node instanceof Node; $node = $node->next()) {
            $rest[] = $node;
        }

        return [$marker->getLiteral(), $rest];
    }

    /**
     * @return list<string>
     */
    private static function markerLiterals(): array
    {
        return array_map(static fn (string $type): string => "[!{$type}]", self::TYPES);
    }
}
