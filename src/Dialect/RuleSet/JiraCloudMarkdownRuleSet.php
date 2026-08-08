<?php

declare(strict_types=1);

namespace Vusys\CmTools\Dialect\RuleSet;

use Vusys\CmTools\Dialect\RuleSet;
use Vusys\CmTools\Dialect\Validator;

/**
 * The constrained Markdown-shortcut syntax JIRA Cloud's rich-text editor accepts while typing
 * into issue descriptions/comments — not classic JIRA wiki markup (`h1.`, `{code}`, `||table||`
 * etc.), which is a different, non-CommonMark notation entirely and out of scope here.
 *
 * This is a best-effort baseline built from JIRA Cloud's documented markdown-shortcut support,
 * not a corpus of real documents — expect to extend `unsupportedConstructs()` as real
 * false-positives/negatives turn up. Known gaps deliberately not modelled yet: JIRA's own
 * `{panel}`/`{code}`/mention/emoji shortcuts have no CommonMark equivalent to flag against.
 */
final class JiraCloudMarkdownRuleSet implements RuleSet
{
    public function name(): string
    {
        return 'JIRA Cloud markdown';
    }

    public function unsupportedConstructs(): array
    {
        return [
            Validator::TABLE,
            Validator::STRIKETHROUGH,
            Validator::TASK_LIST,
            Validator::FOOTNOTE,
            Validator::HTML_BLOCK,
            Validator::HTML_INLINE,
        ];
    }

    public function disallowedHtmlTags(): ?array
    {
        return null;
    }
}
