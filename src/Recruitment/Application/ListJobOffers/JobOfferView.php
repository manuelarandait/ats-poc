<?php

declare(strict_types=1);

namespace App\Recruitment\Application\ListJobOffers;

final readonly class JobOfferView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
    ) {
    }
}
