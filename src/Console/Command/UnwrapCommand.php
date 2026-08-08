<?php

declare(strict_types=1);

namespace Vusys\CmTools\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Vusys\CmTools\Console\Io;
use Vusys\CmTools\Console\TypedInput;
use Vusys\CmTools\Console\WritesErrors;
use Vusys\CmTools\Reflow\Unwrapper;

#[AsCommand(name: 'unwrap', description: 'Collapse fixed-width-wrapped prose back to one line per block')]
final class UnwrapCommand extends Command
{
    use TypedInput;
    use WritesErrors;

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Input file (defaults to stdin)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output file (defaults to stdout)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->stringArgument($input, 'file');
        $outputPath = $this->stringOption($input, 'output');

        try {
            $content = Io::read($file);
            $result = (new Unwrapper)->unwrap($content);

            if ($outputPath !== null && $outputPath !== '-') {
                Io::writeFile($outputPath, $result);
            } else {
                $output->write($result, false, OutputInterface::OUTPUT_RAW);
            }
        } catch (Throwable $e) {
            $this->errorOutput($output)->writeln("<error>cm-tools unwrap: {$e->getMessage()}</error>");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
