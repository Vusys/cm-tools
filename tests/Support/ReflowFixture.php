<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Support;

use RuntimeException;

/**
 * One reflow case from the fixture catalogue in `tests/Fixtures/Reflow`.
 *
 * A fixture is a plain text file of marker-delimited sections, so the markdown it holds can be
 * written and read as markdown rather than as escaped PHP string literals — which matters here
 * because the things most likely to break are exactly the things hardest to see in a PHP
 * string: trailing double-spaces, blank lines inside a blockquote, tabs, and control bytes.
 *
 * The file format, and how to add a case, are documented in `tests/Fixtures/Reflow/README.md`.
 * (Spelling a worked example out here instead would mean putting the section markers in a
 * docblock, where they read as annotations and get reformatted.)
 */
final readonly class ReflowFixture
{
    private const string SECTION = '/^@@@ (\S+)(?: +(.*))?$/';

    /**
     * @param  array<int, string>  $wrapExpectations  keyed by column width
     */
    private function __construct(
        public string $name,
        public string $input,
        public array $wrapExpectations,
        public ?string $unwrapExpectation,
        public ?string $htmlDriftReason,
    ) {}

    /**
     * @return list<self> every fixture in the catalogue, in filename order
     */
    public static function catalogue(): array
    {
        $paths = glob(self::directory().'/*.fixture');

        if ($paths === false || $paths === []) {
            throw new RuntimeException('No fixtures found in '.self::directory());
        }

        sort($paths);

        return array_map(self::fromFile(...), $paths);
    }

    public static function fromFile(string $path): self
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unreadable fixture: {$path}");
        }

        $name = basename($path, '.fixture');
        $sections = self::split($contents, $name);

        $input = null;
        $wrapExpectations = [];
        $unwrapExpectation = null;
        $htmlDriftReason = null;

        foreach ($sections as [$keyword, $argument, $body]) {
            match ($keyword) {
                'note' => null,
                'input' => $input = $body,
                'unwrap' => $unwrapExpectation = $body,
                'html-drift' => $htmlDriftReason = trim($body),
                'wrap' => $wrapExpectations[self::width($argument, $name)] = $body,
                default => throw new RuntimeException("Unknown section '@@@ {$keyword}' in fixture '{$name}'"),
            };
        }

        if ($input === null) {
            throw new RuntimeException("Fixture '{$name}' has no '@@@ input' section");
        }

        if ($wrapExpectations === [] && $unwrapExpectation === null) {
            throw new RuntimeException("Fixture '{$name}' asserts nothing — add a '@@@ wrap <width>' or '@@@ unwrap' section");
        }

        ksort($wrapExpectations);

        return new self($name, $input, $wrapExpectations, $unwrapExpectation, $htmlDriftReason);
    }

    public static function directory(): string
    {
        return dirname(__DIR__).'/Fixtures/Reflow';
    }

    /**
     * @return list<array{0: string, 1: ?string, 2: string}> keyword, argument, body
     */
    private static function split(string $contents, string $name): array
    {
        $sections = [];
        $current = null;
        $lines = [];

        foreach (explode("\n", $contents) as $line) {
            if (preg_match(self::SECTION, $line, $matches) === 1) {
                if ($current !== null) {
                    $sections[] = [$current[0], $current[1], self::normalise($lines)];
                }

                $current = [$matches[1], ($matches[2] ?? '') === '' ? null : $matches[2]];
                $lines = [];

                continue;
            }

            if ($current === null && trim($line) !== '') {
                throw new RuntimeException("Fixture '{$name}' has content before its first '@@@' section");
            }

            $lines[] = $line;
        }

        if ($current !== null) {
            $sections[] = [$current[0], $current[1], self::normalise($lines)];
        }

        return $sections;
    }

    /**
     * Blank lines around a section body are separators between `@@@` markers, not content: the
     * body is trimmed of them and given the single trailing newline every renderer emits.
     * Trailing spaces *within* a line survive — they are how a hard break is spelled.
     *
     * @param  list<string>  $lines
     */
    private static function normalise(array $lines): string
    {
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }

        while ($lines !== [] && trim(end($lines)) === '') {
            array_pop($lines);
        }

        return $lines === [] ? "\n" : implode("\n", $lines)."\n";
    }

    private static function width(?string $argument, string $name): int
    {
        if ($argument === null || preg_match('/^\d+$/', $argument) !== 1) {
            throw new RuntimeException("Fixture '{$name}' has a '@@@ wrap' section without a numeric width");
        }

        return (int) $argument;
    }
}
