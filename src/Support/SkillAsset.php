<?php

declare(strict_types=1);

namespace Vusys\CmTools\Support;

use RuntimeException;

/**
 * The Claude Code skill `cm-tools install-skill` writes out, kept as a real Markdown file under
 * `resources/skill/` rather than a heredoc: it's prose that wants reviewing and diffing on its
 * own, and a test can then read the shipped file and check its frontmatter parses.
 *
 * The path is built with {@see dirname()} rather than a `..` suffix because it has to resolve
 * inside the phar too (`phar:///…/cm-tools.phar/resources/skill/SKILL.md`), where the stream
 * wrapper is doing the resolving and a literal parent-directory segment is best avoided.
 */
final class SkillAsset
{
    /** Both the skill's frontmatter `name` and the directory it installs into. */
    public const string NAME = 'cm-tools';

    public static function path(): string
    {
        return dirname(__DIR__, 2).'/resources/skill/SKILL.md';
    }

    public static function contents(): string
    {
        $contents = @file_get_contents(self::path());

        if ($contents === false) {
            throw new RuntimeException('the bundled skill asset is missing from this build');
        }

        return $contents;
    }
}
