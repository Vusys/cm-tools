<?php

declare(strict_types=1);

namespace Vusys\CmTools\Reflow;

/**
 * Packs a flat list of {@see Tokenizer} atoms into lines: greedily up to `$width` columns
 * when wrapping, or all onto one line when unwrapping (`$width === null`). Either way, an
 * {@see InlineRenderer::HARD_BREAK} atom always forces a new line — unwrapping collapses soft
 * wrapping, not the author's explicit hard breaks.
 */
final class LinePacker
{
    /**
     * @param  list<string>  $atoms
     * @return list<string>
     */
    public static function pack(array $atoms, ?int $width): array
    {
        while ($atoms !== [] && end($atoms) === InlineRenderer::HARD_BREAK) {
            array_pop($atoms);
        }

        if ($atoms === []) {
            return [''];
        }

        $lines = [];
        $current = [];
        $currentLength = 0;

        foreach ($atoms as $atom) {
            if ($atom === InlineRenderer::HARD_BREAK) {
                // Two trailing spaces are CommonMark's hard-break marker — without it, this
                // line break would silently demote to an ordinary (collapsible) soft break
                // once the output is re-parsed.
                $lines[] = implode(' ', $current).'  ';
                $current = [];
                $currentLength = 0;

                continue;
            }

            $atomLength = mb_strlen($atom);
            $separatorLength = $current === [] ? 0 : 1;

            if ($width !== null && $current !== [] && $currentLength + $separatorLength + $atomLength > $width) {
                $lines[] = implode(' ', $current);
                $current = [];
                $currentLength = 0;
                $separatorLength = 0;
            }

            $current[] = $atom;
            $currentLength += $separatorLength + $atomLength;
        }

        $lines[] = implode(' ', $current);

        return $lines;
    }
}
