<?php

declare(strict_types=1);

namespace Vusys\CmTools\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Parser\MarkdownParser;
use Vusys\CmTools\Dialect\Validator;

/**
 * A single shared parser configuration for the whole package: core CommonMark plus every
 * extension we know how to reflow and lint (GFM's autolinks/tables/strikethrough/task
 * lists/disallowed-raw-html, and footnotes). Parsing is deliberately permissive — dialect
 * *restrictions* are enforced afterwards by {@see Validator} walking
 * the resulting AST, not by refusing to parse constructs a target dialect doesn't support.
 */
final class DocumentParser
{
    private static ?Environment $environment = null;

    private static ?MarkdownParser $parser = null;

    public static function parse(string $markdown): Document
    {
        return self::parser()->parse($markdown);
    }

    /**
     * The same configuration {@see self::parse()} uses, exposed so a caller that needs a
     * *renderer* rather than a parser (the fixture suite renders both sides of a reflow to
     * HTML to prove the meaning didn't change) is guaranteed to see the identical extension
     * set, instead of assembling a near-copy that can drift.
     */
    public static function environment(): Environment
    {
        if (! self::$environment instanceof Environment) {
            $environment = new Environment;
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);
            $environment->addExtension(new FootnoteExtension);

            self::$environment = $environment;
        }

        return self::$environment;
    }

    private static function parser(): MarkdownParser
    {
        if (! self::$parser instanceof MarkdownParser) {
            self::$parser = new MarkdownParser(self::environment());
        }

        return self::$parser;
    }
}
