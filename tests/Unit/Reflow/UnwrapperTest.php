<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Reflow;

use PHPUnit\Framework\TestCase;
use Vusys\CmTools\Reflow\Unwrapper;

final class UnwrapperTest extends TestCase
{
    public function test_it_collapses_a_wrapped_paragraph_to_one_line(): void
    {
        $markdown = "This paragraph was\nwrapped across\nseveral lines.\n";

        $result = (new Unwrapper)->unwrap($markdown);

        $this->assertSame("This paragraph was wrapped across several lines.\n", $result);
    }

    public function test_it_still_honours_an_explicit_hard_break(): void
    {
        $markdown = "line one  \nline two\n";

        $result = (new Unwrapper)->unwrap($markdown);

        $this->assertSame("line one  \nline two\n", $result);
    }

    public function test_it_collapses_multiple_paragraphs_independently(): void
    {
        $markdown = "First\nparagraph.\n\nSecond\nparagraph.\n";

        $result = (new Unwrapper)->unwrap($markdown);

        $this->assertSame("First paragraph.\n\nSecond paragraph.\n", $result);
    }
}
