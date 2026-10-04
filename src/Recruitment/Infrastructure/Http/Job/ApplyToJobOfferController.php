<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Job;

use App\Recruitment\Application\FindJobOffer\FindJobOfferQuery;
use App\Recruitment\Application\ListJobOffers\JobOfferView;
use App\Recruitment\Application\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Domain\JobApplication\Candidate\InvalidEmail;
use App\Recruitment\Domain\JobApplication\Candidate\InvalidFullName;
use App\Recruitment\Domain\JobApplication\Candidate\InvalidPhone;
use App\Recruitment\Domain\JobApplication\InvalidCvText;
use App\Recruitment\Domain\JobApplication\InvalidNotes;
use App\Recruitment\Domain\JobOffer\JobOfferNotFound;
use App\Recruitment\Infrastructure\Http\Form\ApplyRequest;
use App\Recruitment\Infrastructure\Http\Form\ApplyType;
use App\Shared\Domain\Bus\Command\CommandBus;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Shared\Domain\DomainError;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * The job description with the apply form next to it.
 */
final readonly class ApplyToJobOfferController
{
    /** Domain rejections shown next to the field they belong to. */
    private const array FIELD_ERRORS = [
        InvalidFullName::class => 'fullName',
        InvalidEmail::class => 'email',
        InvalidPhone::class => 'phone',
        InvalidCvText::class => 'cv',
        InvalidNotes::class => 'notes',
    ];

    public function __construct(
        private QueryBus $queries,
        private CommandBus $commands,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private Environment $twig,
        private ClockInterface $clock,
        #[Autowire(service: 'limiter.job_application')]
        private RateLimiterFactoryInterface $applyLimiter,
    ) {
    }

    #[Route('/jobs/{id}', name: 'jobs_show', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function __invoke(string $id, Request $request): Response
    {
        $offer = $this->offer($id);
        $form = $this->forms->create(ApplyType::class, new ApplyRequest());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Only valid submissions count: a person fixing a typo doesn't burn attempts.
            $limit = $this->applyLimiter->create($request->getClientIp() ?? 'unknown')->consume();

            if (!$limit->isAccepted()) {
                return $this->tooManyApplications($offer, $form, $limit);
            }

            $applicationId = Uuid::v7()->toRfc4122();

            try {
                $this->commands->dispatch($this->command($applicationId, $offer, $form));

                return new RedirectResponse($this->urls->generate('jobs_applied', ['id' => $offer->id, 'applicationId' => $applicationId]), Response::HTTP_SEE_OTHER);
            } catch (DomainError $error) {
                $this->attach($form, $error);
            }
        }

        return $this->page($offer, $form, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * 429 with Retry-After, but still the page: the candidate keeps what they typed.
     *
     * @param FormInterface<ApplyRequest> $form
     */
    private function tooManyApplications(JobOfferView $offer, FormInterface $form, RateLimit $limit): Response
    {
        $seconds = max(1, $limit->getRetryAfter()->getTimestamp() - $this->clock->now()->getTimestamp());
        $minutes = (int) ceil($seconds / 60);
        $form->addError(new FormError(\sprintf('Too many applications from your network. Please try again in %d %s.', $minutes, 1 === $minutes ? 'minute' : 'minutes')));

        return $this->page($offer, $form, Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $seconds]);
    }

    /**
     * @param FormInterface<ApplyRequest> $form
     * @param array<string, string>       $headers
     */
    private function page(JobOfferView $offer, FormInterface $form, int $status, array $headers = []): Response
    {
        return new Response($this->twig->render('jobs/show.html.twig', ['offer' => $offer, 'form' => $form->createView()]), $status, $headers);
    }

    private function offer(string $id): JobOfferView
    {
        try {
            return $this->queries->ask(new FindJobOfferQuery($id));
        } catch (JobOfferNotFound $notFound) {
            throw new NotFoundHttpException('Job offer not found.', $notFound);
        }
    }

    /**
     * @param FormInterface<ApplyRequest> $form
     */
    private function command(string $applicationId, JobOfferView $offer, FormInterface $form): SubmitJobApplicationCommand
    {
        $data = $form->getData();

        return new SubmitJobApplicationCommand(
            id: $applicationId,
            jobOfferId: $offer->id,
            fullName: $data->fullName,
            email: $data->email,
            phone: $data->phone,
            cv: $data->cv,
            notes: $data->notes,
        );
    }

    /**
     * @param FormInterface<ApplyRequest> $form
     */
    private function attach(FormInterface $form, DomainError $error): void
    {
        $field = self::FIELD_ERRORS[$error::class] ?? null;

        (null === $field ? $form : $form->get($field))->addError(new FormError($error->getMessage()));
    }
}
