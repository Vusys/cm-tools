<?php

declare(strict_types=1);

namespace Vusys\CmTools\Reflow;

use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Footnote\Node\FootnoteRef;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Re-serialises the inline children of a block node back to CommonMark source text.
 *
 * The output is one flat string per block; hard line breaks are represented by
 * {@see self::HARD_BREAK} so the caller (the {@see Tokenizer}/line-packing step in
 * {@see BlockRenderer}) can tell a forced break apart from an ordinary wrap-eligible space.
 *
 * Every link/image/autolink is normalised to explicit `[text](url "title")`/`![alt](url)`
 * form, regardless of whether the source used a bare autolink (`<https://…>`, `www.…`) — all
 * three parse to the same AST node in league/commonmark, and a fixed-width reflow already
 * rewrites the surrounding source, so preserving the original spelling isn't meaningful here.
 * Escaping is deliberately conservative: characters that could be reinterpreted as markup are
 * always backslash-escaped in plain text, even where the CommonMark flanking rules would have
 * made it unambiguous — correctness (never silently change meaning) matters more than minimal
 * escaping for a round-trip tool.
 */
final class InlineRenderer
{
    public const string HARD_BREAK = "\x01";

    private const array ESCAPE_CHARS = ['\\', '`', '*', '_', '~', '[', ']', '<', '>'];

    /**
     * Drops the sentinel byte from source markdown.
     *
     * {@see self::HARD_BREAK} is an in-band marker inside the rendered inline string, so a
     * literal U+0001 in the source is indistinguishable from a real hard break and gets
     * turned into one. Removing it at the door makes the marker unambiguous by construction,
     * which is a stronger guarantee than sanitising each source-derived literal on the way
     * out: the byte can arrive through text, code spans, raw HTML, a link URL or a link
     * title, and any call site missed there reintroduces the collision silently.
     *
     * U+0001 carries no meaning in CommonMark, so dropping it costs nothing real. Note the
     * document is the *only* thing this makes lossy — it is not a round-trip guarantee for
     * arbitrary bytes, and the fixture that covers it is marked as intentionally altering the
     * rendered output for that reason.
     */
    public static function withoutSentinels(string $markdown): string
    {
        return str_replace(self::HARD_BREAK, '', $markdown);
    }

    /**
     * @param  iterable<Node>  $children
     */
    public function render(iterable $children): string
    {
        $out = '';

        foreach ($children as $child) {
            $out .= $this->renderNode($child);
        }

        return $out;
    }

    private function renderNode(Node $node): string
    {
        return match (true) {
            $node instanceof Text => $this->escape($node->getLiteral()),
            $node instanceof Code => $this->renderCodeSpan($node->getLiteral()),
            $node instanceof Emphasis => $node->getOpeningDelimiter().$this->render($node->children()).$node->getClosingDelimiter(),
            $node instanceof Strong => $node->getOpeningDelimiter().$this->render($node->children()).$node->getClosingDelimiter(),
            $node instanceof Strikethrough => $node->getOpeningDelimiter().$this->render($node->children()).$node->getClosingDelimiter(),
            $node instanceof Image => '!'.$this->renderLink($node->getUrl(), $node->getTitle(), $this->render($node->children())),
            $node instanceof Link => $this->renderLink($node->getUrl(), $node->getTitle(), $this->render($node->children())),
            $node instanceof HtmlInline => $node->getLiteral(),
            $node instanceof FootnoteRef => '[^'.$node->getReference()->getLabel().']',
            $node instanceof TaskListItemMarker => $node->isChecked() ? '[x]' : '[ ]',
            $node instanceof Newline => $node->getType() === Newline::HARDBREAK ? self::HARD_BREAK : ' ',
            default => $this->render($node->children()),
        };
    }

    private function renderLink(string $url, ?string $title, string $label): string
    {
        $tail = $url;

        if ($title !== null && $title !== '') {
            $tail .= ' "'.str_replace('"', '\\"', $title).'"';
        }

        return '['.$label.']('.$tail.')';
    }

    private function renderCodeSpan(string $literal): string
    {
        $longestRun = 0;

        if (preg_match_all('/`+/', $literal, $matches) > 0) {
            foreach ($matches[0] as $run) {
                $longestRun = max($longestRun, strlen($run));
            }
        }

        $fence = str_repeat('`', $longestRun + 1);
        $needsPadding = $literal === ''
            || str_starts_with($literal, '`')
            || str_ends_with($literal, '`')
            || trim($literal) === '';
        $padding = $needsPadding ? ' ' : '';

        return $fence.$padding.$literal.$padding.$fence;
    }

    private function escape(string $text): string
    {
        return str_replace(self::ESCAPE_CHARS, array_map(static fn (string $c): string => '\\'.$c, self::ESCAPE_CHARS), $text);
    }
}
