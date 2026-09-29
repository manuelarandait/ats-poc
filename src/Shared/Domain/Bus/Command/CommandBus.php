<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Command;

interface CommandBus
{
    /**
     * Runs the command synchronously; returns nothing (CQRS). Domain errors
     * raised by the handler are rethrown as they are.
     */
    public function dispatch(Command $command): void;
}
