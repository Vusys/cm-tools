<?php

declare(strict_types=1);

namespace Vusys\CmTools\Console;

use RuntimeException;

/**
 * File-or-stdin reading shared by every command: a missing path or `-` means stdin. Writing
 * to a file is the only file-side-effect commands need — stdout goes through Symfony's
 * `OutputInterface` instead, so it's capturable in tests and safe from the console
 * formatter misinterpreting `<...>` sequences in arbitrary Markdown/HTML content.
 */
final class Io
{
    public static function read(?string $path): string
    {
        if ($path === null || $path === '-') {
            $contents = stream_get_contents(STDIN);

            return $contents === false ? '' : $contents;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("could not read \"{$path}\"");
        }

        return $contents;
    }

    public static function writeFile(string $path, string $content): void
    {
        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException("could not write \"{$path}\"");
        }
    }
}
