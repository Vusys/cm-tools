<?php

declare(strict_types=1);

namespace Vusys\CmTools\Dialect;

use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\Footnote\Node\Footnote;
use League\CommonMark\Extension\Footnote\Node\FootnoteRef;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;

/**
 * Walks a parsed document once per {@see RuleSet} and reports every construct the AST contains
 * that the rule set's {@see RuleSet::unsupportedConstructs()} declares unsupported.
 */
final class Validator
{
    public const string TABLE = 'table';

    public const string STRIKETHROUGH = 'strikethrough';

    public const string TASK_LIST = 'task-list';

    public const string FOOTNOTE = 'footnote';

    public const string HTML_BLOCK = 'html-block';

    public const string HTML_INLINE = 'html-inline';

    /**
     * @return list<Violation>
     */
    public function validate(Document $document, RuleSet $ruleSet): array
    {
        $unsupported = $ruleSet->unsupportedConstructs();
        $disallowedTags = $ruleSet->disallowedHtmlTags();
        $violations = [];

        foreach ($document->iterator() as $node) {
            foreach ($this->constructsOf($node, $ruleSet, $unsupported, $disallowedTags) as [$construct, $message]) {
                $violations[] = new Violation($construct, $message, $this->nearestLine($node));
            }
        }

        return $violations;
    }

    /**
     * @param  list<string>  $unsupported
     * @param  list<string>|null  $disallowedTags
     * @return list<array{0: string, 1: string}>
     */
    private function constructsOf(Node $node, RuleSet $ruleSet, array $unsupported, ?array $disallowedTags): array
    {
        $found = [];

        if ($node instanceof Table && in_array(self::TABLE, $unsupported, true)) {
            $found[] = [self::TABLE, "GFM table syntax is not supported by {$ruleSet->name()}."];
        }

        if ($node instanceof Strikethrough && in_array(self::STRIKETHROUGH, $unsupported, true)) {
            $found[] = [self::STRIKETHROUGH, "`~~strikethrough~~` is not supported by {$ruleSet->name()}."];
        }

        if ($node instanceof TaskListItemMarker && in_array(self::TASK_LIST, $unsupported, true)) {
            $found[] = [self::TASK_LIST, "Task list checkboxes are not supported by {$ruleSet->name()}."];
        }

        if (($node instanceof Footnote || $node instanceof FootnoteRef) && in_array(self::FOOTNOTE, $unsupported, true)) {
            $found[] = [self::FOOTNOTE, "Footnotes are not supported by {$ruleSet->name()}."];
        }

        if ($node instanceof HtmlBlock) {
            $found = [...$found, ...$this->htmlConstructs(self::HTML_BLOCK, $node->getLiteral(), $ruleSet, $unsupported, $disallowedTags)];
        }

        if ($node instanceof HtmlInline) {
            return [...$found, ...$this->htmlConstructs(self::HTML_INLINE, $node->getLiteral(), $ruleSet, $unsupported, $disallowedTags)];
        }

        return $found;
    }

    /**
     * @param  list<string>  $unsupported
     * @param  list<string>|null  $disallowedTags
     * @return list<array{0: string, 1: string}>
     */
    private function htmlConstructs(string $construct, string $literal, RuleSet $ruleSet, array $unsupported, ?array $disallowedTags): array
    {
        if (in_array($construct, $unsupported, true)) {
            return [[$construct, "Raw HTML is not supported by {$ruleSet->name()}."]];
        }

        if ($disallowedTags !== null && preg_match('/<\/?\s*([a-zA-Z][a-zA-Z0-9-]*)/', $literal, $matches) === 1) {
            $tag = strtolower($matches[1]);

            if (in_array($tag, $disallowedTags, true)) {
                return [[$construct, "The <{$tag}> tag is stripped or escaped by {$ruleSet->name()}."]];
            }
        }

        return [];
    }

    private function nearestLine(Node $node): ?int
    {
        $current = $node;

        while ($current instanceof Node) {
            if ($current instanceof AbstractBlock && $current->getStartLine() !== null) {
                return $current->getStartLine();
            }

            $current = $current->parent();
        }

        return null;
    }
}
