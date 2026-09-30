<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\JobApplication;

use App\Recruitment\Application\ListJobOffers\ListJobOffersQuery;
use App\Recruitment\Application\SearchJobApplications\SearchJobApplicationsQuery;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\UnknownJobApplicationStatus;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Shared\Domain\ValueObject\InvalidUuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class ListJobApplicationsController
{
    /** Turbo Frame holding the results: filtering reloads only this part. */
    public const string RESULTS_FRAME = 'applications-results';

    public function __construct(
        private QueryBus $queries,
        private Environment $twig,
    ) {
    }

    #[Route('/applications', name: 'applications_index', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $filters = [
            'q' => $request->query->getString('q'),
            'status' => $request->query->getString('status'),
            'position' => $request->query->getString('position'),
        ];

        try {
            $page = $this->queries->ask(new SearchJobApplicationsQuery(
                status: $filters['status'],
                jobOfferId: $filters['position'],
                search: $filters['q'],
                page: $request->query->getInt('page', 1),
            ));
        } catch (UnknownJobApplicationStatus|InvalidUuid $invalidFilter) {
            throw new BadRequestHttpException($invalidFilter->getMessage(), $invalidFilter);
        }

        $context = ['page' => $page, 'filters' => $filters, 'frame' => self::RESULTS_FRAME];

        // A frame request (live filtering, pagination, polling) only needs the results.
        if (self::RESULTS_FRAME === $request->headers->get('Turbo-Frame')) {
            return new Response($this->twig->render('applications/_results.html.twig', $context));
        }

        return new Response($this->twig->render('applications/index.html.twig', $context + [
            'offers' => $this->queries->ask(new ListJobOffersQuery()),
            'statuses' => JobApplicationStatus::cases(),
        ]));
    }
}
