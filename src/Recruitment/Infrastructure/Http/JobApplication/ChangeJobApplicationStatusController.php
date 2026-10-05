<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\JobApplication;

use App\Recruitment\Application\ChangeJobApplicationStatus\ChangeJobApplicationStatusCommand;
use App\Recruitment\Domain\JobApplication\InvalidStatusTransition;
use App\Recruitment\Domain\JobApplication\JobApplicationNotFound;
use App\Recruitment\Domain\JobApplication\UnknownJobApplicationStatus;
use App\Shared\Domain\Bus\Command\CommandBus;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class ChangeJobApplicationStatusController
{
    public function __construct(
        private CommandBus $commands,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/applications/{id}/status', name: 'applications_change_status', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function __invoke(string $id, Request $request): RedirectResponse
    {
        if (!$this->csrf->isTokenValid(new CsrfToken('change-status-'.$id, $request->request->getString('_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $status = $request->request->getString('status');

        try {
            $this->commands->dispatch(new ChangeJobApplicationStatusCommand($id, $status));
            $this->flash($request, 'success', \sprintf('Status updated to "%s".', str_replace('_', ' ', $status)));
        } catch (JobApplicationNotFound $notFound) {
            throw new NotFoundHttpException('Job application not found.', $notFound);
        } catch (InvalidStatusTransition|UnknownJobApplicationStatus $rejected) {
            $this->flash($request, 'error', $rejected->getMessage());
        }

        return new RedirectResponse($this->urls->generate('applications_show', ['id' => $id]), Response::HTTP_SEE_OTHER);
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
