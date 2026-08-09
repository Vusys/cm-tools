<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Reflow;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vusys\CmTools\Reflow\LineStartEscaper;

final class LineStartEscaperTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function dangerousLines(): iterable
    {
        yield 'bullet dash' => ['- echo foxtrot', '\\- echo foxtrot'];
        yield 'bullet plus' => ['+ echo foxtrot', '\\+ echo foxtrot'];
        yield 'atx heading' => ['# echo foxtrot', '\\# echo foxtrot'];
        yield 'deepest atx heading' => ['###### echo', '\\###### echo'];
        yield 'ordered with period' => ['1. echo', '1\\. echo'];
        yield 'ordered with paren' => ['1) echo', '1\\) echo'];
        yield 'multi-digit ordered' => ['1234. echo', '1234\\. echo'];
        yield 'bare dash' => ['-', '\\-'];
        yield 'setext h2 underline' => ['---', '\\---'];
        yield 'setext h1 underline' => ['===', '\\==='];
        yield 'underline with trailing space' => ['--- ', '\\--- '];
        yield 'spaced thematic break' => ['- - -', '\\- - -'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function safeLines(): iterable
    {
        yield 'ordinary prose' => ['echo foxtrot golf'];
        yield 'dash without a following space' => ['-echo foxtrot'];
        yield 'hash without a following space' => ['#echo foxtrot'];
        yield 'seven hashes is not a heading' => ['####### echo'];
        yield 'ten digits is too many for a marker' => ['1234567890. echo'];
        yield 'digits without a delimiter' => ['1 echo'];
        yield 'dash later in the line' => ['echo - foxtrot'];
        yield 'dashes with other content' => ['--- echo'];
        yield 'mixed run is not an underline' => ['-=-='];
        yield 'already escaped' => ['\\- echo foxtrot'];
        yield 'empty line' => [''];
    }

    #[DataProvider('dangerousLines')]
    public function test_it_escapes_a_line_that_would_re_read_as_block_markup(string $line, string $expected): void
    {
        $this->assertSame($expected, LineStartEscaper::escape($line));
    }

    #[DataProvider('safeLines')]
    public function test_it_leaves_a_line_that_cannot_be_block_markup_alone(string $line): void
    {
        $this->assertSame($line, LineStartEscaper::escape($line));
    }
}
