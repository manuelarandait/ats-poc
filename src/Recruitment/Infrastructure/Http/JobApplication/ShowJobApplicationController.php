<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\JobApplication;

use App\Recruitment\Application\FindJobApplication\FindJobApplicationQuery;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Shared\Domain\Bus\Query\QueryBus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Twig\Environment;

final readonly class ShowJobApplicationController
{
    public function __construct(
        private QueryBus $queries,
        private Environment $twig,
    ) {
    }

    #[Route('/applications/{id}', name: 'applications_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        try {
            $application = $this->queries->ask(new FindJobApplicationQuery($id));
        } catch (JobApplicationNotFound $notFound) {
            throw new NotFoundHttpException('Job application not found.', $notFound);
        }

        return new Response($this->twig->render('applications/show.html.twig', ['application' => $application]));
    }
}
