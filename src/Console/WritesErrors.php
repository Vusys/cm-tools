<?php

declare(strict_types=1);

namespace Vusys\CmTools\Console;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Routes error messages to the real stderr channel when one exists (any real terminal run,
 * via Symfony's `ConsoleOutput`), and falls back to the given output otherwise —
 * `CommandTester` in tests uses a plain `StreamOutput`, which doesn't implement
 * {@see ConsoleOutputInterface} and has no separate error channel to route to.
 */
trait WritesErrors
{
    private function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
