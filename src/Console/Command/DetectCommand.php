<?php

declare(strict_types=1);

namespace Vusys\CmTools\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Vusys\CmTools\Console\Io;
use Vusys\CmTools\Console\TypedInput;
use Vusys\CmTools\Console\WritesErrors;
use Vusys\CmTools\Dialect\RuleSetRegistry;
use Vusys\CmTools\Dialect\Validator;
use Vusys\CmTools\Support\DocumentParser;

#[AsCommand(name: 'detect', description: 'Report which known dialects a document is compatible with')]
final class DetectCommand extends Command
{
    use TypedInput;
    use WritesErrors;

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Input file (defaults to stdin)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->stringArgument($input, 'file');

        try {
            $content = Io::read($file);
        } catch (Throwable $e) {
            $this->errorOutput($output)->writeln("<error>cm-tools detect: {$e->getMessage()}</error>");

            return self::FAILURE;
        }

        $document = DocumentParser::parse($content);
        $validator = new Validator;

        foreach ((new RuleSetRegistry)->all() as $ruleSet) {
            $violations = $validator->validate($document, $ruleSet);
            $status = $violations === [] ? 'compatible' : count($violations).' violation(s)';

            $output->writeln(sprintf('%-26s %s', $ruleSet->name(), $status), OutputInterface::OUTPUT_RAW);
        }

        return self::SUCCESS;
    }
}
