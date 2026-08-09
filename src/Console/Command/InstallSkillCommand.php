<?php

declare(strict_types=1);

namespace Vusys\CmTools\Console\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Vusys\CmTools\Console\Io;
use Vusys\CmTools\Console\TypedInput;
use Vusys\CmTools\Console\WritesErrors;
use Vusys\CmTools\Support\SkillAsset;

/**
 * Installs the bundled Claude Code skill, so onboarding an assistant to cm-tools is one command
 * rather than copying a `SKILL.md` out of the README. The skill itself is
 * {@see SkillAsset}; everything here is where to put it.
 */
#[AsCommand(name: 'install-skill', description: 'Install a Claude Code skill that teaches an assistant how and when to use cm-tools')]
final class InstallSkillCommand extends Command
{
    use TypedInput;
    use WritesErrors;

    private const string SKILLS_DIRECTORY = '.claude/skills';

    protected function configure(): void
    {
        $this
            ->addOption('user', null, InputOption::VALUE_NONE, 'Install for the current user (~/.claude) instead of this project')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing SKILL.md')
            ->addOption('print', null, InputOption::VALUE_NONE, 'Write the skill to stdout and touch nothing on disk');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $skill = SkillAsset::contents();

            if ($this->boolOption($input, 'print')) {
                $output->write($skill, false, OutputInterface::OUTPUT_RAW);

                return self::SUCCESS;
            }

            $path = $this->destination($this->boolOption($input, 'user'));

            if (is_file($path) && ! $this->boolOption($input, 'force')) {
                throw new RuntimeException("\"{$path}\" already exists (pass --force to overwrite)");
            }

            Io::ensureDirectory(dirname($path));
            Io::writeFile($path, $skill);
        } catch (Throwable $e) {
            $this->errorOutput($output)->writeln("<error>cm-tools install-skill: {$e->getMessage()}</error>");

            return self::FAILURE;
        }

        $output->writeln("cm-tools: installed skill to {$path}", OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    private function destination(bool $userScope): string
    {
        $root = $userScope ? $this->homeDirectory() : $this->workingDirectory();

        return $root.'/'.self::SKILLS_DIRECTORY.'/'.SkillAsset::NAME.'/SKILL.md';
    }

    private function homeDirectory(): string
    {
        foreach (['HOME', 'USERPROFILE'] as $variable) {
            $value = getenv($variable);

            if (is_string($value) && $value !== '') {
                return rtrim($value, '/\\');
            }
        }

        throw new RuntimeException('could not determine the home directory (neither HOME nor USERPROFILE is set)');
    }

    private function workingDirectory(): string
    {
        $cwd = getcwd();

        if ($cwd === false) {
            throw new RuntimeException('could not determine the current working directory');
        }

        return rtrim($cwd, '/\\');
    }
}
