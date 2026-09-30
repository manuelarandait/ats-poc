<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine;

use App\Recruitment\Domain\JobOffer\JobOffer;
use App\Recruitment\Domain\JobOffer\JobOfferId;
use App\Recruitment\Domain\JobOffer\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineJobOfferRepository implements JobOfferRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(JobOffer $offer): void
    {
        $this->entityManager->persist($offer);
        $this->entityManager->flush();
    }

    public function find(JobOfferId $id): ?JobOffer
    {
        return $this->entityManager->find(JobOffer::class, $id);
    }
}
