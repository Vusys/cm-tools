<?php

declare(strict_types=1);

namespace Vusys\CmTools\Dialect\RuleSet;

use Vusys\CmTools\Dialect\RuleSet;

/**
 * GitHub Flavored Markdown, as rendered on github.com: tables, strikethrough, task lists,
 * autolinks, and footnotes are all supported. Raw HTML is allowed, except for a small set of
 * tags GitHub strips or escapes for safety (the same list `league/commonmark`'s own
 * `DisallowedRawHtmlExtension` ships) — those are flagged even though they parse fine, because
 * they won't render as authored.
 */
final class GitHubFlavoredMarkdownRuleSet implements RuleSet
{
    public function name(): string
    {
        return 'GitHub Flavored Markdown';
    }

    public function unsupportedConstructs(): array
    {
        return [];
    }

    public function disallowedHtmlTags(): array
    {
        return [
            'title',
            'textarea',
            'style',
            'xmp',
            'iframe',
            'noembed',
            'noframes',
            'script',
            'plaintext',
        ];
    }
}
