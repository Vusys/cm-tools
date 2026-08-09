<?php

declare(strict_types=1);

namespace Vusys\CmTools\Reflow;

/**
 * Escapes prose that would be re-read as block markup because of where it sits on a line.
 *
 * These characters are only dangerous in first position: `alpha - bravo` is prose, but break
 * the line before the dash and `- bravo` is a list item. That makes the danger a property of
 * the *packing*, not of the text, so it can't be handled in {@see InlineRenderer::escape()} —
 * which is also why it shouldn't be: escaping every `-` in every paragraph to cover the few
 * that land first would be noise in the output.
 *
 * {@see InlineRenderer::ESCAPE_CHARS} already covers the markers that are dangerous anywhere
 * (`*`, `_`, `~`, backtick, `<`, `>`, `[`, `]`), so what's left here is `-`, `+`, `#`, ordered
 * list markers, and setext underlines.
 */
final class LineStartEscaper
{
    /**
     * A bullet marker or an ATX heading marker: `- x`, `+ x`, `# x` … `###### x`, and each of
     * those markers alone on the line. Seven or more `#` is not a heading, so it is left be.
     */
    private const string LEADING_MARKER = '/^(?:[-+]|#{1,6})(?: |$)/';

    /**
     * A line that is nothing but a run of `-` or `=`. This is the nasty one: a setext
     * underline reaches *backwards* and retroactively turns the line above it into a heading,
     * so the corruption lands on text that was already emitted and looked safe. A `-` or `=`
     * run is also how a thematic break is spelled.
     */
    private const string UNDERLINE = '/^(?:-+|=+)[ \t]*$/';

    /**
     * An ordered list marker: `1. x`, `1) x`. CommonMark allows at most nine digits.
     */
    private const string ORDERED_MARKER = '/^(\d{1,9})[.)](?: |$)/';

    public static function escape(string $line): string
    {
        if (preg_match(self::LEADING_MARKER, $line) === 1 || preg_match(self::UNDERLINE, $line) === 1) {
            return '\\'.$line;
        }

        if (preg_match(self::ORDERED_MARKER, $line, $matches) === 1) {
            // The digits themselves can't be escaped — a backslash only escapes ASCII
            // punctuation, so `\1` would be a literal backslash followed by a 1. Escaping the
            // delimiter instead is the spelling CommonMark documents for this.
            $digits = $matches[1];

            return $digits.'\\'.substr($line, strlen($digits));
        }

        return $line;
    }
}
