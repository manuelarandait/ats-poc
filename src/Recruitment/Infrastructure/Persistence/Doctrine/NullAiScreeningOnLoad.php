<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Doctrine;

use App\Recruitment\Domain\JobApplication\AiScreening;
use App\Recruitment\Domain\JobApplication\JobApplication;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

/**
 * Doctrine always instantiates embeddables, even when all their columns are
 * NULL, leaving an AiScreening with uninitialised properties. The domain says
 * "no screening yet" with null, so we restore that after loading.
 */
#[AsDoctrineListener(event: Events::postLoad)]
final class NullAiScreeningOnLoad
{
    public function postLoad(PostLoadEventArgs $args): void
    {
        $application = $args->getObject();

        if (!$application instanceof JobApplication) {
            return;
        }

        $screening = new \ReflectionProperty(JobApplication::class, 'aiScreening');
        $value = $screening->getValue($application);

        if ($value instanceof AiScreening && !new \ReflectionProperty(AiScreening::class, 'summary')->isInitialized($value)) {
            $screening->setValue($application, null);
        }
    }
}
