<?php

declare(strict_types=1);

namespace Vusys\CmTools\Dialect;

/**
 * A target Markdown dialect, described declaratively as the set of constructs it doesn't
 * support (see {@see Validator}'s `CONSTRUCT_*` constants) rather than as its own AST-walking
 * code — every rule set is otherwise identical mechanically, only the allow-list differs.
 */
interface RuleSet
{
    public function name(): string;

    /**
     * @return list<string> a subset of {@see Validator}'s `CONSTRUCT_*` constants
     */
    public function unsupportedConstructs(): array;

    /**
     * Lower-cased raw HTML tag names this dialect strips or escapes, on top of whatever
     * blanket html-block/html-inline support {@see self::unsupportedConstructs()} declares.
     * Return null if raw HTML support is all-or-nothing for this dialect.
     *
     * @return list<string>|null
     */
    public function disallowedHtmlTags(): ?array;
}
