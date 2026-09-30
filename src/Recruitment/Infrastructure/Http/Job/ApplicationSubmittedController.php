<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Job;

use App\Recruitment\Application\FindJobOffer\FindJobOfferQuery;
use App\Recruitment\Domain\JobOffer\JobOfferNotFound;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Shared\Domain\ValueObject\InvalidUuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Confirmation after applying (Post/Redirect/Get: reloading never resubmits).
 */
final readonly class ApplicationSubmittedController
{
    public function __construct(
        private QueryBus $queries,
        private Environment $twig,
    ) {
    }

    #[Route('/jobs/{id}/applied/{applicationId}', name: 'jobs_applied', methods: ['GET'])]
    public function __invoke(string $id, string $applicationId): Response
    {
        try {
            $offer = $this->queries->ask(new FindJobOfferQuery($id));
        } catch (JobOfferNotFound|InvalidUuid $notFound) {
            throw new NotFoundHttpException('Job offer not found.', $notFound);
        }

        return new Response($this->twig->render('jobs/applied.html.twig', ['offer' => $offer, 'applicationId' => $applicationId]));
    }
}
