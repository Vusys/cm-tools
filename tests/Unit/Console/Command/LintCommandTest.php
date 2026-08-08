<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Console\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Vusys\CmTools\Console\Command\LintCommand;

final class LintCommandTest extends TestCase
{
    private string $input;

    protected function setUp(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'cm-tools-in-');
        $this->assertIsString($input);
        $this->input = $input;
    }

    protected function tearDown(): void
    {
        @unlink($this->input);
    }

    public function test_a_compatible_document_exits_zero(): void
    {
        file_put_contents($this->input, "# Title\n\nJust prose.\n");

        $tester = new CommandTester(new LintCommand);
        $exit = $tester->execute(['file' => $this->input, '--dialect' => 'github']);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('compatible with GitHub Flavored Markdown', $tester->getDisplay());
    }

    public function test_violations_exit_one_and_are_listed(): void
    {
        file_put_contents($this->input, "~~gone~~\n");

        $tester = new CommandTester(new LintCommand);
        $exit = $tester->execute(['file' => $this->input, '--dialect' => 'jira-cloud']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('[strikethrough]', $tester->getDisplay());
    }

    public function test_an_unknown_dialect_exits_one(): void
    {
        file_put_contents($this->input, "hi\n");

        $tester = new CommandTester(new LintCommand);
        $exit = $tester->execute(['file' => $this->input, '--dialect' => 'nonexistent']);

        $this->assertSame(1, $exit);
    }
}
