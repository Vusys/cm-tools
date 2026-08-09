<?php

declare(strict_types=1);

namespace Vusys\CmTools\Reflow;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\Footnote\Node\Footnote;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;

/**
 * Walks a parsed {@see Document} and re-serialises it to CommonMark source, either wrapped to
 * a fixed column width or unwrapped to one line per logical block.
 *
 * `$indent` is threaded through every render call purely so a nested paragraph (inside a
 * blockquote or list item) knows how much width its ancestors' prefixes ("> ", "  ") will
 * consume, and can wrap narrower accordingly — the prefixes themselves are applied once, by
 * the immediate parent, via {@see self::prefixLines()}.
 */
final readonly class BlockRenderer
{
    private InlineRenderer $inline;

    public function __construct(private ?int $width)
    {
        $this->inline = new InlineRenderer;
    }

    public function render(Document $document): string
    {
        return rtrim($this->renderBlocks($document->children(), '')).PHP_EOL;
    }

    /**
     * Blocks are normally separated by a blank line. `$separator` exists for the one context
     * where that is wrong: inside a *tight* list item, where a blank line between the item's
     * paragraph and its nested sublist would promote the whole list to loose — and looseness
     * is a property of the entire list, so a single nested item re-wraps every sibling's
     * content in a `<p>`.
     *
     * @param  iterable<Node>  $blocks
     */
    private function renderBlocks(iterable $blocks, string $indent, string $separator = "\n\n"): string
    {
        $rendered = [];

        foreach ($blocks as $block) {
            $text = $this->renderBlock($block, $indent);

            if ($text !== '') {
                $rendered[] = $text;
            }
        }

        return implode($separator, $rendered);
    }

    private function renderBlock(Node $block, string $indent): string
    {
        return match (true) {
            $block instanceof Paragraph => $this->renderParagraph($block, $indent),
            $block instanceof Heading => $this->renderHeading($block),
            $block instanceof ThematicBreak => '---',
            $block instanceof BlockQuote => $this->prefixLines($this->renderBlocks($block->children(), $indent.'> '), '> ', '> ', '>'),
            $block instanceof FencedCode => $this->renderFencedCode($block),
            $block instanceof IndentedCode => $this->renderIndentedCode($block),
            $block instanceof HtmlBlock => rtrim($block->getLiteral()),
            $block instanceof ListBlock => $this->renderList($block, $indent),
            $block instanceof Table => $this->renderTable($block),
            $block instanceof Footnote => $this->renderFootnote($block, $indent),
            default => $this->renderBlocks($block->children(), $indent),
        };
    }

    /**
     * Escaping happens after packing, and against the *unprefixed* line, which is the correct
     * position twice over: only then is it known which atom actually landed first, and a line
     * that will be prefixed with `- `/`> ` needs the check just as much — `- - echo` is a
     * nested list. It can push a line one column over the requested width, which is the right
     * trade against silently emitting a different document.
     *
     * A {@see AlertMarker::split()}-leading paragraph packs the marker as a bare line of its
     * own, ahead of the rest — never through {@see InlineRenderer}, which would backslash-escape
     * its brackets like any other literal `[`/`]` — so a wide enough width (or `unwrap`'s
     * unlimited one) never fills the marker's line with body text and turns a GitHub alert
     * callout back into a plain quote.
     */
    private function renderParagraph(Paragraph $paragraph, string $indent): string
    {
        $available = $this->width === null ? null : max(1, $this->width - mb_strlen($indent));
        $split = AlertMarker::split($paragraph);

        if ($split === null) {
            $atoms = Tokenizer::tokenize($this->inline->render($paragraph->children()));
            $lines = LinePacker::pack($atoms, $available);

            return implode("\n", array_map(LineStartEscaper::escape(...), $lines));
        }

        [$marker, $rest] = $split;
        $atoms = Tokenizer::tokenize($this->inline->render($rest));
        $lines = $atoms === [] ? [$marker] : [$marker, ...LinePacker::pack($atoms, $available)];

        return implode("\n", array_map(LineStartEscaper::escape(...), $lines));
    }

    private function renderHeading(Heading $heading): string
    {
        $text = $this->collapseHardBreaks($this->inline->render($heading->children()));

        return str_repeat('#', $heading->getLevel()).' '.$text;
    }

    private function renderFencedCode(FencedCode $node): string
    {
        $fence = str_repeat($node->getChar(), max(3, $node->getLength()));
        $info = $node->getInfo();
        $opening = $info === null || $info === '' ? $fence : $fence.$info;

        return $opening."\n".rtrim($node->getLiteral(), "\n")."\n".$fence;
    }

    /**
     * Indented code blocks are normalised to fenced code — sidesteps having to recompute
     * 4-space indentation once this block is itself nested under a list/blockquote prefix,
     * and is unambiguously equivalent CommonMark.
     */
    private function renderIndentedCode(IndentedCode $node): string
    {
        return "```\n".rtrim($node->getLiteral(), "\n")."\n```";
    }

    private function renderList(ListBlock $list, string $indent): string
    {
        $data = $list->getListData();
        $number = $data->start ?? 1;
        $items = [];

        // One separator governs both joins: between an item's own blocks, and between items.
        // They are the same decision — a blank line anywhere inside a list makes it loose.
        $separator = $list->isTight() ? "\n" : "\n\n";

        foreach ($list->children() as $item) {
            if (! $item instanceof ListItem) {
                continue;
            }

            if ($data->type === ListBlock::TYPE_ORDERED) {
                $marker = $number.($data->delimiter === ListBlock::DELIM_PAREN ? ') ' : '. ');
                $number++;
            } else {
                $marker = ($data->bulletChar ?? '-').' ';
            }

            $markerIndent = str_repeat(' ', mb_strlen($marker));
            $body = $this->renderBlocks($item->children(), $indent.$markerIndent, $separator);
            $items[] = $this->prefixLines($body, $marker, $markerIndent, '');
        }

        return implode($separator, $items);
    }

    private function renderFootnote(Footnote $footnote, string $indent): string
    {
        $marker = '[^'.$footnote->getReference()->getLabel().']: ';
        $markerIndent = str_repeat(' ', mb_strlen($marker));
        $body = $this->renderBlocks($footnote->children(), $indent.$markerIndent);

        return $this->prefixLines($body, $marker, $markerIndent, '');
    }

    private function renderTable(Table $table): string
    {
        $head = null;
        $bodyRows = [];

        foreach ($table->children() as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }

            if ($section->isHead()) {
                $head = $section;

                continue;
            }

            foreach ($section->children() as $row) {
                if ($row instanceof TableRow) {
                    $bodyRows[] = $row;
                }
            }
        }

        if (! $head instanceof TableSection) {
            return '';
        }

        $headerRow = null;

        foreach ($head->children() as $row) {
            if ($row instanceof TableRow) {
                $headerRow = $row;

                break;
            }
        }

        if (! $headerRow instanceof TableRow) {
            return '';
        }

        $headerCells = $this->cellsOf($headerRow);
        $aligns = array_map(static fn (TableCell $cell): ?string => $cell->getAlign(), $headerCells);
        $headerText = array_map($this->tableCellText(...), $headerCells);
        $bodyText = array_map(fn (TableRow $row): array => array_map($this->tableCellText(...), $this->cellsOf($row)), $bodyRows);

        $columns = count($headerCells);
        $widths = [];

        for ($i = 0; $i < $columns; $i++) {
            $width = max(3, mb_strlen($headerText[$i] ?? ''));

            foreach ($bodyText as $row) {
                $width = max($width, mb_strlen($row[$i] ?? ''));
            }

            $widths[$i] = $width;
        }

        $lines = [$this->tableRowLine($headerText, $widths), $this->tableSeparatorLine($aligns, $widths)];

        foreach ($bodyText as $row) {
            $lines[] = $this->tableRowLine($row, $widths);
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<TableCell>
     */
    private function cellsOf(TableRow $row): array
    {
        $cells = [];

        foreach ($row->children() as $cell) {
            if ($cell instanceof TableCell) {
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    private function tableCellText(TableCell $cell): string
    {
        $text = $this->collapseHardBreaks($this->inline->render($cell->children()));

        return str_replace('|', '\\|', $text);
    }

    /**
     * @param  list<string>  $cells
     * @param  array<int, int>  $widths
     */
    private function tableRowLine(array $cells, array $widths): string
    {
        $padded = [];

        foreach ($widths as $i => $width) {
            $padded[] = $this->padTo($cells[$i] ?? '', $width);
        }

        return '| '.implode(' | ', $padded).' |';
    }

    /**
     * @param  array<int, ?string>  $aligns
     * @param  array<int, int>  $widths
     */
    private function tableSeparatorLine(array $aligns, array $widths): string
    {
        $segments = [];

        foreach ($widths as $i => $width) {
            $segments[] = match ($aligns[$i] ?? null) {
                TableCell::ALIGN_LEFT => ':'.str_repeat('-', $width - 1),
                TableCell::ALIGN_RIGHT => str_repeat('-', $width - 1).':',
                TableCell::ALIGN_CENTER => ':'.str_repeat('-', max(1, $width - 2)).':',
                default => str_repeat('-', $width),
            };
        }

        return '| '.implode(' | ', $segments).' |';
    }

    private function padTo(string $text, int $width): string
    {
        $padding = $width - mb_strlen($text);

        return $padding > 0 ? $text.str_repeat(' ', $padding) : $text;
    }

    private function collapseHardBreaks(string $text): string
    {
        return str_replace(InlineRenderer::HARD_BREAK, ' ', $text);
    }

    /**
     * Applies a block's marker to the lines of its rendered body.
     *
     * Blank lines are the whole difficulty here, and the right answer differs per caller,
     * which is why `$blankPrefix` is a parameter rather than a constant `''`: a list item or
     * footnote continues across a genuinely empty line, but a blockquote does not — a bare
     * blank line *terminates* a quote, so one quote holding several blocks would come back
     * out as several sibling quotes.
     *
     * The first line is never blank-cased at all. It always carries the marker, because an
     * empty list item is still an item: dropping its `- `/`1. ` deletes it from the list, and
     * for an ordered list renumbers every item that followed it.
     */
    private function prefixLines(string $text, string $firstPrefix, string $contPrefix, string $blankPrefix): string
    {
        $lines = explode("\n", $text);

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $lines[$i] = $line === '' ? rtrim($firstPrefix) : $firstPrefix.$line;

                continue;
            }

            $lines[$i] = $line === '' ? $blankPrefix : $contPrefix.$line;
        }

        return implode("\n", $lines);
    }
}
