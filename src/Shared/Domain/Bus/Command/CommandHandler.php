<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Command;

/**
 * Marker for command handlers (one public __invoke per handler). Wiring to the
 * actual bus is done in infrastructure, so use cases stay framework-free.
 */
interface CommandHandler
{
}
