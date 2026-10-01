<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security\Http;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

/**
 * Renders the recruiter login form. Checking the credentials is done by the
 * firewall's form_login (config/packages/security.yaml), not here.
 */
final readonly class LoginController
{
    public function __construct(
        private AuthenticationUtils $authenticationUtils,
        private Security $security,
        private UrlGeneratorInterface $urls,
        private Environment $twig,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function __invoke(): Response
    {
        if (null !== $this->security->getUser()) {
            return new RedirectResponse($this->urls->generate('applications_index'));
        }

        return new Response($this->twig->render('security/login.html.twig', [
            'lastEmail' => $this->authenticationUtils->getLastUsername(),
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
        ]));
    }
}
