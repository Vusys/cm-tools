<?php

declare(strict_types=1);

namespace Vusys\CmTools\Tests\Unit\Console\Command;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Tester\CommandTester;
use Vusys\CmTools\Console\Command\InstallSkillCommand;
use Vusys\CmTools\Support\SkillAsset;

/**
 * The command's whole job is *where* the skill lands, so every case here runs against a throwaway
 * working directory and a throwaway `HOME` — kept as separate subdirectories so that a scope
 * picking the wrong root is a visible failure rather than two paths that happen to coincide.
 */
final class InstallSkillCommandTest extends TestCase
{
    private string $workspace;

    private string $project;

    private string $home;

    private string $originalCwd;

    private string|false $originalHome;

    protected function setUp(): void
    {
        $base = tempnam(sys_get_temp_dir(), 'cm-tools-skill-');
        $this->assertIsString($base);
        unlink($base);
        mkdir($base.'/project', 0o777, true);
        mkdir($base.'/home', 0o777, true);

        $workspace = realpath($base);
        $this->assertIsString($workspace);

        $this->workspace = $workspace;
        $this->project = $workspace.'/project';
        $this->home = $workspace.'/home';

        $cwd = getcwd();
        $this->assertIsString($cwd);
        $this->originalCwd = $cwd;
        $this->originalHome = getenv('HOME');

        chdir($this->project);
        putenv('HOME='.$this->home);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if ($this->originalHome === false) {
            putenv('HOME');
        } else {
            putenv('HOME='.$this->originalHome);
        }

        $this->deleteRecursively($this->workspace);
    }

    public function test_it_installs_into_the_project_by_default(): void
    {
        $tester = new CommandTester(new InstallSkillCommand);

        $exit = $tester->execute([]);

        $this->assertSame(0, $exit);
        $this->assertSame(SkillAsset::contents(), file_get_contents($this->skillPath($this->project)));
        $this->assertFileDoesNotExist($this->skillPath($this->home));
        $this->assertStringContainsString($this->skillPath($this->project), $tester->getDisplay());
    }

    public function test_the_user_flag_installs_into_the_home_directory(): void
    {
        $tester = new CommandTester(new InstallSkillCommand);

        $exit = $tester->execute(['--user' => true]);

        $this->assertSame(0, $exit);
        $this->assertSame(SkillAsset::contents(), file_get_contents($this->skillPath($this->home)));
        $this->assertFileDoesNotExist($this->skillPath($this->project));
    }

    public function test_it_refuses_to_overwrite_an_existing_skill(): void
    {
        $path = $this->skillPath($this->project);
        mkdir(dirname($path), 0o777, true);
        file_put_contents($path, 'hand-written');

        $tester = new CommandTester(new InstallSkillCommand);

        $exit = $tester->execute([]);

        $this->assertSame(1, $exit);
        $this->assertSame('hand-written', file_get_contents($path));
        $this->assertStringContainsString('--force', $tester->getDisplay());
    }

    public function test_force_overwrites_an_existing_skill(): void
    {
        $path = $this->skillPath($this->project);
        mkdir(dirname($path), 0o777, true);
        file_put_contents($path, 'hand-written');

        $tester = new CommandTester(new InstallSkillCommand);

        $exit = $tester->execute(['--force' => true]);

        $this->assertSame(0, $exit);
        $this->assertSame(SkillAsset::contents(), file_get_contents($path));
    }

    public function test_print_writes_the_skill_to_stdout_and_creates_nothing(): void
    {
        $tester = new CommandTester(new InstallSkillCommand);

        $exit = $tester->execute(['--print' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString(SkillAsset::contents(), $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->project.'/.claude');
        $this->assertDirectoryDoesNotExist($this->home.'/.claude');
    }

    private function skillPath(string $root): string
    {
        return $root.'/.claude/skills/'.SkillAsset::NAME.'/SKILL.md';
    }

    private function deleteRecursively(string $directory): void
    {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            $this->assertInstanceOf(SplFileInfo::class, $entry);

            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}
