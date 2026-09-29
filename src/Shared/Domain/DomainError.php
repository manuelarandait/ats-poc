<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Base for every broken business rule. Lets the edge (HTTP, consumers) tell
 * domain errors apart from technical failures.
 */
abstract class DomainError extends \DomainException
{
}
