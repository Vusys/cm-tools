<?php

declare(strict_types=1);

namespace Vusys\CmTools\Dialect\RuleSet;

use Vusys\CmTools\Dialect\RuleSet;
use Vusys\CmTools\Dialect\Validator;

/**
 * The base CommonMark spec — no GitHub Flavored Markdown extensions, no footnotes. Raw HTML is
 * part of the core spec, so it's allowed here. Useful as a lowest-common-denominator check:
 * a document that passes this rule set will render identically wherever CommonMark is
 * supported, with no renderer-specific extension needed.
 */
final class CommonMarkStrictRuleSet implements RuleSet
{
    public function name(): string
    {
        return 'CommonMark (strict)';
    }

    public function unsupportedConstructs(): array
    {
        return [
            Validator::TABLE,
            Validator::STRIKETHROUGH,
            Validator::TASK_LIST,
            Validator::FOOTNOTE,
        ];
    }

    public function disallowedHtmlTags(): ?array
    {
        return null;
    }
}
