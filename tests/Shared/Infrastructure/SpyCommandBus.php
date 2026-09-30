<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Domain\Bus\Command\Command;
use App\Shared\Domain\Bus\Command\CommandBus;

final class SpyCommandBus implements CommandBus
{
    /** @var list<Command> */
    public private(set) array $dispatched = [];

    public function dispatch(Command $command): void
    {
        $this->dispatched[] = $command;
    }
}
