<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Console\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Vusys\CmTools\Console\Command\WrapCommand;

final class WrapCommandTest extends TestCase
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

    public function test_it_wraps_a_file_to_the_given_width_and_writes_the_output_file(): void
    {
        file_put_contents($this->input, "A sentence long enough that it will need to wrap across more than one line.\n");

        $tester = new CommandTester(new WrapCommand);
        $exit = $tester->execute(['file' => $this->input, '--width' => '20', '--output' => $this->output]);

        $this->assertSame(0, $exit);
        $result = (string) file_get_contents($this->output);

        foreach (explode("\n", trim($result)) as $line) {
            $this->assertLessThanOrEqual(20, mb_strlen($line));
        }
    }

    public function test_it_writes_to_the_console_output_when_no_output_option_is_given(): void
    {
        file_put_contents($this->input, "hello world\n");

        $tester = new CommandTester(new WrapCommand);
        $tester->execute(['file' => $this->input]);

        $this->assertSame("hello world\n", $tester->getDisplay());
    }

    public function test_a_non_positive_width_is_rejected(): void
    {
        file_put_contents($this->input, "hi\n");

        $tester = new CommandTester(new WrapCommand);
        $exit = $tester->execute(['file' => $this->input, '--width' => '0']);

        $this->assertSame(1, $exit);
    }

    public function test_a_missing_input_file_exits_one(): void
    {
        $tester = new CommandTester(new WrapCommand);
        $exit = $tester->execute(['file' => '/nonexistent/path/does-not-exist.md']);

        $this->assertSame(1, $exit);
    }
}
