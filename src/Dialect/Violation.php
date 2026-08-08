<?php

declare(strict_types=1);

namespace Vusys\CmTools\Dialect;

final readonly class Violation
{
    public function __construct(
        public string $construct,
        public string $message,
        public ?int $line,
    ) {}
}
