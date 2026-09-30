<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class HomeController
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('jobs_index'));
    }
}
