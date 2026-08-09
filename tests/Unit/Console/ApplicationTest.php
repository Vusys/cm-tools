<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Tester\ApplicationTester;
use Vusys\CmTools\Console\Application;

final class ApplicationTest extends TestCase
{
    public function test_version_flag_prints_the_version_and_exits_zero(): void
    {
        $tester = new ApplicationTester($this->application());

        $exit = $tester->run(['--version' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString(Application::VERSION, $tester->getDisplay());
    }

    public function test_it_registers_every_command(): void
    {
        $names = array_keys($this->application()->all());

        $this->assertContains('wrap', $names);
        $this->assertContains('unwrap', $names);
        $this->assertContains('lint', $names);
        $this->assertContains('detect', $names);
        $this->assertContains('install-skill', $names);
    }

    public function test_an_unknown_command_exits_non_zero(): void
    {
        $application = $this->application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);

        $this->expectException(CommandNotFoundException::class);

        $tester->run(['command' => 'nonsense']);
    }

    private function application(): Application
    {
        $application = new Application;
        $application->setAutoExit(false);

        return $application;
    }
}
