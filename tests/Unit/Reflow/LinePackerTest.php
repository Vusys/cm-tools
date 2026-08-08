<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Reflow;

use PHPUnit\Framework\TestCase;
use Vusys\CmTools\Reflow\InlineRenderer;
use Vusys\CmTools\Reflow\LinePacker;

final class LinePackerTest extends TestCase
{
    public function test_it_packs_words_up_to_the_width(): void
    {
        $lines = LinePacker::pack(['aaaa', 'bb', 'cc', 'dddd'], 8);

        $this->assertSame(['aaaa bb', 'cc dddd'], $lines);
    }

    public function test_null_width_joins_everything_onto_one_line(): void
    {
        $lines = LinePacker::pack(['a', 'b', 'c'], null);

        $this->assertSame(['a b c'], $lines);
    }

    public function test_a_hard_break_always_forces_a_new_line(): void
    {
        $lines = LinePacker::pack(['a', InlineRenderer::HARD_BREAK, 'b'], null);

        $this->assertSame(['a  ', 'b'], $lines);
    }

    public function test_a_single_atom_longer_than_the_width_is_not_split(): void
    {
        $lines = LinePacker::pack(['https://example.com/a-very-long-url-indeed'], 10);

        $this->assertSame(['https://example.com/a-very-long-url-indeed'], $lines);
    }

    public function test_empty_atoms_produce_a_single_empty_line(): void
    {
        $this->assertSame([''], LinePacker::pack([], 40));
    }
}
