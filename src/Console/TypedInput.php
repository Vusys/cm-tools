<?php

declare(strict_types=1);

namespace Vusys\CmTools\Console;

use Symfony\Component\Console\Input\InputInterface;

/**
 * `InputInterface::getArgument()`/`getOption()` return `mixed` (they also cover array-valued
 * options), so PHPStan can't narrow a cast without a genuine runtime check. These do that
 * check rather than overriding the inferred type with a `@var`/`assert()` — every command's
 * arguments/options are actually scalar, so the `is_string`/`is_numeric`/`=== true` branch is
 * always the one taken; the `null`/default fallback only matters to the type checker.
 */
trait TypedInput
{
    private function stringArgument(InputInterface $input, string $name): ?string
    {
        $value = $input->getArgument($name);

        return is_string($value) ? $value : null;
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) ? $value : null;
    }

    private function intOption(InputInterface $input, string $name, int $default): int
    {
        $value = $input->getOption($name);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function boolOption(InputInterface $input, string $name): bool
    {
        return $input->getOption($name) === true;
    }
}
