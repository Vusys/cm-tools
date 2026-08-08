<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Console\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Vusys\CmTools\Console\Command\DetectCommand;

final class DetectCommandTest extends TestCase
{
    public function test_it_reports_every_registered_dialect(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'cm-tools-in-');
        $this->assertIsString($input);
        file_put_contents($input, "# Title\n\nJust prose.\n");

        try {
            $tester = new CommandTester(new DetectCommand);
            $exit = $tester->execute(['file' => $input]);

            $this->assertSame(0, $exit);
            $display = $tester->getDisplay();
            $this->assertMatchesRegularExpression('/CommonMark \(strict\).*GitHub Flavored Markdown.*JIRA Cloud markdown/s', $display);
        } finally {
            unlink($input);
        }
    }
}
