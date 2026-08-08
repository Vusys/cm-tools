<?php

declare(strict_types=1);

namespace Vusys\CmTools\Reflow;

/**
 * Splits a single rendered inline-markdown string into break-safe atoms.
 *
 * An atom is either a run of ordinary text (itself splittable at plain spaces) or one
 * unbreakable span: a backtick code span, a `<...>` autolink/raw-HTML tag, the `](url
 * "title")` tail of a link/image, or an {@see InlineRenderer::HARD_BREAK} marker. Splitting a
 * string this way — rather than walking the AST for wrapping too — decouples "turn the AST
 * into markdown" (InlineRenderer's job) from "decide where lines may break" (this class), so
 * either can be tested and changed independently.
 */
final class Tokenizer
{
    /**
     * @return list<string> atoms in order; the exact string InlineRenderer::HARD_BREAK marks
     *                      a forced line break rather than a wrap-eligible word boundary
     */
    public static function tokenize(string $text): array
    {
        $atoms = [];
        $current = '';
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            $char = $text[$i];

            if ($char === InlineRenderer::HARD_BREAK) {
                if ($current !== '') {
                    $atoms[] = $current;
                    $current = '';
                }

                $atoms[] = InlineRenderer::HARD_BREAK;
                $i++;

                continue;
            }

            if ($char === ' ') {
                if ($current !== '') {
                    $atoms[] = $current;
                    $current = '';
                }

                $i++;

                continue;
            }

            if ($char === '`') {
                [$span, $i] = self::consumeCodeSpan($text, $i);
                $current .= $span;

                continue;
            }

            if ($char === '<') {
                [$span, $i] = self::consumeUntilInclusive($text, $i, '>');
                $current .= $span;

                continue;
            }

            if ($char === ']' && ($text[$i + 1] ?? '') === '(') {
                [$span, $i] = self::consumeBalancedParens($text, $i + 1);
                $current .= ']'.$span;

                continue;
            }

            $current .= $char;
            $i++;
        }

        if ($current !== '') {
            $atoms[] = $current;
        }

        return $atoms;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function consumeCodeSpan(string $text, int $start): array
    {
        $length = strlen($text);
        $fenceEnd = $start;

        while ($fenceEnd < $length && $text[$fenceEnd] === '`') {
            $fenceEnd++;
        }

        $fenceLength = $fenceEnd - $start;
        $fence = str_repeat('`', $fenceLength);
        $closeAt = strpos($text, $fence, $fenceEnd);

        if ($closeAt === false) {
            // No matching close fence — not actually a code span, treat the backticks as
            // literal characters so they still participate in normal word-wrapping.
            return [$fence, $fenceEnd];
        }

        $end = $closeAt + $fenceLength;

        return [substr($text, $start, $end - $start), $end];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function consumeUntilInclusive(string $text, int $start, string $delimiter): array
    {
        $end = strpos($text, $delimiter, $start + 1);

        if ($end === false) {
            return [$text[$start], $start + 1];
        }

        $end++;

        return [substr($text, $start, $end - $start), $end];
    }

    /**
     * @return array{0: string, 1: int} the span starting at the opening "(" (inclusive)
     */
    private static function consumeBalancedParens(string $text, int $openAt): array
    {
        $length = strlen($text);

        if (($text[$openAt] ?? '') !== '(') {
            return ['', $openAt];
        }

        $depth = 0;
        $i = $openAt;

        while ($i < $length) {
            if ($text[$i] === '(') {
                $depth++;
            } elseif ($text[$i] === ')') {
                $depth--;

                if ($depth === 0) {
                    $i++;

                    break;
                }
            }

            $i++;
        }

        return [substr($text, $openAt, $i - $openAt), $i];
    }
}
