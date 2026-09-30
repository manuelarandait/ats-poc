<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Query;

/**
 * Request for data; never changes state. The template declares the response
 * type, so QueryBus::ask() is fully typed for static analysis.
 *
 * @template TResponse
 */
interface Query
{
}
