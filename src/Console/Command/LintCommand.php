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
use Vusys\CmTools\Dialect\RuleSet;
use Vusys\CmTools\Dialect\RuleSetRegistry;
use Vusys\CmTools\Dialect\Validator;
use Vusys\CmTools\Support\DocumentParser;

#[AsCommand(name: 'lint', description: 'Check a document against a target Markdown dialect, exiting non-zero on violations')]
final class LintCommand extends Command
{
    use TypedInput;
    use WritesErrors;

    private const string DEFAULT_DIALECT = 'github';

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Input file (defaults to stdin)')
            ->addOption('dialect', null, InputOption::VALUE_REQUIRED, 'Target dialect: commonmark, github, jira-cloud', self::DEFAULT_DIALECT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registry = new RuleSetRegistry;
        $dialectKey = $this->stringOption($input, 'dialect') ?? self::DEFAULT_DIALECT;
        $ruleSet = $registry->get($dialectKey);

        if (! $ruleSet instanceof RuleSet) {
            $this->errorOutput($output)->writeln(sprintf(
                '<error>cm-tools lint: unknown dialect "%s" (known: %s)</error>',
                $dialectKey,
                implode(', ', $registry->keys()),
            ));

            return self::FAILURE;
        }

        $file = $this->stringArgument($input, 'file');

        try {
            $content = Io::read($file);
        } catch (Throwable $e) {
            $this->errorOutput($output)->writeln("<error>cm-tools lint: {$e->getMessage()}</error>");

            return self::FAILURE;
        }

        $document = DocumentParser::parse($content);
        $violations = (new Validator)->validate($document, $ruleSet);

        if ($violations === []) {
            $output->writeln(sprintf('cm-tools: compatible with %s', $ruleSet->name()), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $label = $file ?? '(stdin)';

        foreach ($violations as $violation) {
            $output->writeln(
                sprintf('%s:%s: [%s] %s', $label, $violation->line ?? '?', $violation->construct, $violation->message),
                OutputInterface::OUTPUT_RAW,
            );
        }

        return self::FAILURE;
    }
}
