<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Job;

use App\Recruitment\Application\ListJobOffers\ListJobOffersQuery;
use App\Shared\Domain\Bus\Query\QueryBus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class ListJobOffersController
{
    public function __construct(
        private QueryBus $queries,
        private Environment $twig,
    ) {
    }

    #[Route('/jobs', name: 'jobs_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response($this->twig->render('jobs/index.html.twig', [
            'offers' => $this->queries->ask(new ListJobOffersQuery()),
        ]));
    }
}
