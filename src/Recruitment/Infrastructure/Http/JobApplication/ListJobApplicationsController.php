<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\JobApplication;

use App\Recruitment\Application\FindJobApplicationStats\FindJobApplicationStatsQuery;
use App\Recruitment\Application\ListJobOffers\ListJobOffersQuery;
use App\Recruitment\Application\SearchJobApplications\JobApplicationPage;
use App\Recruitment\Application\SearchJobApplications\SearchJobApplicationsQuery;
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

    /** Page sizes offered in the UI; anything else falls back to the default. */
    public const array PER_PAGE_OPTIONS = [10, 20, 50];
    private const int DEFAULT_PER_PAGE = 20;

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
        $perPage = $request->query->getInt('perPage', self::DEFAULT_PER_PAGE);
        $perPage = \in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        try {
            $page = $this->search($filters, $request->query->getInt('page', 1), $perPage);

            // A page past the end (e.g. after narrowing the filters) shows the last one instead of nothing.
            if ([] === $page->items && $page->page > $page->pages()) {
                $page = $this->search($filters, $page->pages(), $perPage);
            }
        } catch (UnknownJobApplicationStatus|InvalidUuid $invalidFilter) {
            throw new BadRequestHttpException($invalidFilter->getMessage(), $invalidFilter);
        }

        $context = [
            'page' => $page,
            'stats' => $this->queries->ask(new FindJobApplicationStatsQuery($filters['position'], $filters['q'])),
            'filters' => $filters,
            'frame' => self::RESULTS_FRAME,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            // Query parameters that every page link must keep (empty ones and the default page size are dropped).
            'linkParams' => array_filter($filters) + (self::DEFAULT_PER_PAGE === $perPage ? [] : ['perPage' => $perPage]),
        ];

        // A frame request (live filtering, pagination, polling) only needs the results.
        if (self::RESULTS_FRAME === $request->headers->get('Turbo-Frame')) {
            return new Response($this->twig->render('applications/_results.html.twig', $context));
        }

        return new Response($this->twig->render('applications/index.html.twig', $context + [
            'offers' => $this->queries->ask(new ListJobOffersQuery()),
        ]));
    }

    /**
     * @param array{q: string, status: string, position: string} $filters
     */
    private function search(array $filters, int $page, int $perPage): JobApplicationPage
    {
        return $this->queries->ask(new SearchJobApplicationsQuery(
            status: $filters['status'],
            jobOfferId: $filters['position'],
            search: $filters['q'],
            page: $page,
            perPage: $perPage,
        ));
    }
}
