<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Reflow;

use PHPUnit\Framework\TestCase;
use Vusys\CmTools\Reflow\InlineRenderer;
use Vusys\CmTools\Reflow\Tokenizer;

final class TokenizerTest extends TestCase
{
    public function test_it_splits_plain_text_on_spaces(): void
    {
        $this->assertSame(['hello', 'world'], Tokenizer::tokenize('hello world'));
    }

    public function test_it_collapses_multiple_spaces(): void
    {
        $this->assertSame(['hello', 'world'], Tokenizer::tokenize('hello   world'));
    }

    public function test_it_keeps_a_code_span_with_internal_spaces_as_one_atom(): void
    {
        $this->assertSame(
            ['run', '`git commit -m "msg"`', 'now'],
            Tokenizer::tokenize('run `git commit -m "msg"` now'),
        );
    }

    public function test_it_keeps_an_autolink_as_one_atom(): void
    {
        $this->assertSame(
            ['see', '<https://example.com/a b>'],
            Tokenizer::tokenize('see <https://example.com/a b>'),
        );
    }

    public function test_it_keeps_a_link_destination_tail_attached_to_the_last_word_of_the_label(): void
    {
        $tokens = Tokenizer::tokenize('a [click here](https://example.com "a title") b');

        $this->assertSame(['a', '[click', 'here](https://example.com "a title")', 'b'], $tokens);
    }

    public function test_hard_break_marker_becomes_its_own_atom(): void
    {
        $tokens = Tokenizer::tokenize('one'.InlineRenderer::HARD_BREAK.'two');

        $this->assertSame(['one', InlineRenderer::HARD_BREAK, 'two'], $tokens);
    }
}
