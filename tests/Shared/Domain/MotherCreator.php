<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use Faker\Factory;
use Faker\Generator;

/**
 * Single Faker instance for all Object Mothers (so unique() works across them),
 * usable in plain unit tests without booting the kernel or Foundry.
 */
final class MotherCreator
{
    private static ?Generator $faker = null;

    public static function faker(): Generator
    {
        return self::$faker ??= Factory::create();
    }
}
