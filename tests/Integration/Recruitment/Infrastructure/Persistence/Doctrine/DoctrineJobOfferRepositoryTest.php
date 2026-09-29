<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Persistence\Doctrine;

use App\Recruitment\Domain\JobOffer\JobOfferRepository;
use App\Tests\Recruitment\Domain\JobOffer\JobOfferMother;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineJobOfferRepositoryTest extends KernelTestCase
{
    public function test_it_persists_and_reloads_a_job_offer(): void
    {
        $repository = self::getContainer()->get(JobOfferRepository::class);
        $offer = JobOfferMother::create(title: 'Senior PHP Developer', description: 'Symfony, DDD, RabbitMQ.');

        $repository->save($offer);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloaded = $repository->find($offer->id);

        self::assertNotNull($reloaded);
        self::assertSame('Senior PHP Developer', $reloaded->title);
        self::assertSame('Symfony, DDD, RabbitMQ.', $reloaded->description);
    }
}
