<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Console\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Vusys\CmTools\Console\Command\UnwrapCommand;

final class UnwrapCommandTest extends TestCase
{
    private string $input;

    private string $output;

    protected function setUp(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'cm-tools-in-');
        $output = tempnam(sys_get_temp_dir(), 'cm-tools-out-');
        $this->assertIsString($input);
        $this->assertIsString($output);
        $this->input = $input;
        $this->output = $output;
    }

    protected function tearDown(): void
    {
        @unlink($this->input);
        @unlink($this->output);
    }

    public function test_it_collapses_a_wrapped_file_and_writes_the_output_file(): void
    {
        file_put_contents($this->input, "This paragraph\nwas wrapped\nacross lines.\n");

        $tester = new CommandTester(new UnwrapCommand);
        $exit = $tester->execute(['file' => $this->input, '--output' => $this->output]);

        $this->assertSame(0, $exit);
        $this->assertSame("This paragraph was wrapped across lines.\n", file_get_contents($this->output));
    }

    public function test_it_writes_to_console_output_when_no_output_option_is_given(): void
    {
        file_put_contents($this->input, "one\ntwo\n");

        $tester = new CommandTester(new UnwrapCommand);
        $tester->execute(['file' => $this->input]);

        $this->assertSame("one two\n", $tester->getDisplay());
    }

    public function test_a_missing_input_file_exits_one(): void
    {
        $tester = new CommandTester(new UnwrapCommand);
        $exit = $tester->execute(['file' => '/nonexistent/path/does-not-exist.md']);

        $this->assertSame(1, $exit);
    }
}
