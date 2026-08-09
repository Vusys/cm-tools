<?php

declare(strict_types=1);

namespace Vusys\CmTools\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use Vusys\CmTools\Console\Command\DetectCommand;
use Vusys\CmTools\Console\Command\LintCommand;
use Vusys\CmTools\Console\Command\UnwrapCommand;
use Vusys\CmTools\Console\Command\WrapCommand;

final class Application extends SymfonyApplication
{
    public const string VERSION = '0.2.0';

    public function __construct()
    {
        parent::__construct('cm-tools', self::VERSION);

        $this->addCommands([
            new WrapCommand,
            new UnwrapCommand,
            new LintCommand,
            new DetectCommand,
        ]);
    }
}
