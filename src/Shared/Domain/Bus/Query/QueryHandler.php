<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Query;

/**
 * Marker for query handlers (one public __invoke per handler returning the
 * query's response).
 */
interface QueryHandler
{
}
