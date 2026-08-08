<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Reflow;

use PHPUnit\Framework\TestCase;
use Vusys\CmTools\Reflow\Unwrapper;
use Vusys\CmTools\Reflow\Wrapper;

final class WrapperTest extends TestCase
{
    public function test_it_wraps_a_long_paragraph_to_the_given_width(): void
    {
        $markdown = "This is a fairly long sentence that should be wrapped across several lines once a narrow width is applied to it.\n";

        $result = (new Wrapper)->wrap($markdown, 20);

        foreach (explode("\n", trim($result)) as $line) {
            $this->assertLessThanOrEqual(20, mb_strlen($line));
        }
    }

    public function test_it_never_splits_an_inline_code_span(): void
    {
        $markdown = "Run `composer install --no-interaction` to set things up.\n";

        $result = (new Wrapper)->wrap($markdown, 20);

        $this->assertStringContainsString('`composer install --no-interaction`', $result);
    }

    public function test_it_preserves_a_fenced_code_block_verbatim(): void
    {
        $markdown = "```php\n<?php\n\$x = 'a string with more than twenty characters in it';\n```\n";

        $result = (new Wrapper)->wrap($markdown, 20);

        $this->assertStringContainsString("\$x = 'a string with more than twenty characters in it';", $result);
    }

    public function test_wrap_then_unwrap_round_trips_the_content(): void
    {
        $markdown = "This is a paragraph with **bold**, *italic*, and a [link](https://example.com \"Example\") in it, long enough to wrap.\n";

        $wrapped = (new Wrapper)->wrap($markdown, 30);
        $unwrapped = (new Unwrapper)->unwrap($wrapped);

        $this->assertSame(
            (new Unwrapper)->unwrap($markdown),
            $unwrapped,
        );
    }

    public function test_it_preserves_a_hard_line_break(): void
    {
        $markdown = "line one  \nline two\n";

        $result = (new Wrapper)->wrap($markdown, 80);

        $this->assertSame("line one  \nline two\n", $result);
    }

    public function test_it_renders_a_bullet_list_with_hanging_indent(): void
    {
        $markdown = "- first item that is long enough to require wrapping across two lines\n- second\n";

        $result = (new Wrapper)->wrap($markdown, 30);
        $lines = explode("\n", trim($result));

        $this->assertStringStartsWith('- first', $lines[0]);
        $this->assertStringStartsWith('  ', $lines[1]);
    }

    public function test_it_renders_a_blockquote_with_prefix_on_every_line(): void
    {
        $markdown = "> quoted text that is long enough that it needs to wrap across more than one line\n";

        $result = (new Wrapper)->wrap($markdown, 30);

        foreach (explode("\n", trim($result)) as $line) {
            $this->assertStringStartsWith('> ', $line);
        }
    }

    public function test_it_reconstructs_a_gfm_table(): void
    {
        $markdown = "| A | B |\n| --- | --- |\n| 1 | 2 |\n";

        $result = (new Wrapper)->wrap($markdown, 80);

        $this->assertStringContainsString('| A', $result);
        $this->assertStringContainsString('| 1', $result);
    }
}
