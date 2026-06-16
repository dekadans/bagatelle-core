<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Console;

/**
 * Interface for resolving a list of available console commands.
 * The output of this is intended as input to a CommandLoaderInterface implementation.
 */
interface CommandDefinitionLoaderInterface
{
    public function load(): array;
}