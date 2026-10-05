<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Http;

use App\Recruitment\Domain\JobApplication\CvText;
use App\Recruitment\Domain\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\JobApplication\Notes;
use App\Recruitment\Domain\JobApplication\SkillMatch;
use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use App\Tests\Shared\Infrastructure\Security\RecruiterLogin;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Detail page: candidate data, pasted CV, AI summary + score, status and
 * timestamps; recruiters move the application through the pipeline.
 */
final class JobApplicationDetailTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = RecruiterLogin::as(self::createClient());
    }

    public function test_it_shows_candidate_data_cv_ai_outputs_status_and_timestamps(): void
    {
        $application = JobApplicationFactory::new()
            ->forOffer(JobOfferFactory::createOne(['title' => 'Senior PHP Developer']))
            ->candidate('Jane Doe', 'jane@example.com', '+34 600 123 456')
            ->with(['cv' => CvText::fromString("Jane Doe\nPHP, 6 years"), 'notes' => Notes::fromNullable('Available in October')])
            ->appliedAt('2026-09-01 10:00:00')
            ->screened(88, 'Strong PHP profile.')
            ->create();

        $this->client->request('GET', '/applications/'.$application->id->value);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Jane Doe');
        self::assertSelectorTextContains('main', 'Senior PHP Developer');
        self::assertSelectorTextContains('main', 'jane@example.com');
        self::assertSelectorTextContains('main', '+34600123456');
        self::assertSame("Jane Doe\nPHP, 6 years", $this->client->getCrawler()->filter('pre')->text(normalizeWhitespace: false), 'The CV keeps its original layout.');
        self::assertSelectorTextContains('main', 'Available in October');
        self::assertSelectorTextContains('section[aria-labelledby="ai-title"]', 'Strong PHP profile.');
        self::assertSelectorTextContains('section[aria-labelledby="ai-title"]', '88');
        self::assertSelectorExists('[role="img"][aria-label="Relevance score 88 out of 100"] circle.stroke-emerald-500');
        self::assertSelectorTextContains('section[aria-labelledby="status-title"]', 'Received');
        self::assertSelectorTextContains('section[aria-labelledby="timeline-title"]', 'Sep 1, 2026 · 10:00 UTC');
        self::assertSelectorTextContains('section[aria-labelledby="timeline-title"]', 'Sep 1, 2026 · 10:01 UTC');
        self::assertSelectorNotExists('[data-controller="poll"]');
    }

    public function test_the_screening_shows_skill_by_skill_what_the_cv_covers_next_to_the_offer(): void
    {
        $offer = JobOfferFactory::createOne(['title' => 'Senior PHP Developer', 'description' => "Build our backend.\n\nRequirements: PHP, Symfony.\n\nNice to have: Kafka."]);
        $application = JobApplicationFactory::new()->forOffer($offer)->screened(70, 'Backend engineer.', [
            SkillMatch::create('PHP', true, true),
            SkillMatch::create('Symfony', true, false),
            SkillMatch::create('Kafka', false, false),
        ])->create();

        $crawler = $this->client->request('GET', '/applications/'.$application->id->value);

        $chips = $crawler->filter('section[aria-labelledby="ai-title"] li')->each(static fn (Crawler $chip): string => trim($chip->text()));
        self::assertSame(['PHP: in the CV', 'Symfony: missing', 'Kafka: missing'], $chips);
        self::assertSelectorTextContains('section[aria-labelledby="ai-title"]', 'Required 1 of 2 in the CV');
        self::assertSelectorTextContains('section[aria-labelledby="position-title"]', 'Senior PHP Developer');
        self::assertSelectorTextContains('section[aria-labelledby="position-title"]', 'Build our backend.');
        self::assertSelectorTextContains('section[aria-labelledby="position-title"] h3', 'Requirements');
    }

    public function test_an_analysis_made_before_the_skill_breakdown_existed_shows_only_the_summary(): void
    {
        $application = JobApplicationFactory::new()->screened(70, 'Backend engineer.')->create();

        $this->client->request('GET', '/applications/'.$application->id->value);

        self::assertSelectorTextContains('section[aria-labelledby="ai-title"]', 'Backend engineer.');
        self::assertSelectorNotExists('section[aria-labelledby="ai-title"] li');
    }

    public function test_while_the_ai_is_analysing_the_page_refreshes_itself(): void
    {
        $application = JobApplicationFactory::createOne();

        $this->client->request('GET', '/applications/'.$application->id->value);

        self::assertSelectorTextContains('main', 'Analysing…');
        self::assertSelectorExists('turbo-frame#application-detail [data-controller="poll"]');
    }

    public function test_other_applications_from_the_same_email_are_listed_with_a_caveat(): void
    {
        $application = JobApplicationFactory::new()->candidate('Jane Doe', 'jane@example.com')->create();
        $other = JobApplicationFactory::new()->forOffer(JobOfferFactory::createOne(['title' => 'Data Engineer']))->candidate('Jane Doe', 'jane@example.com')->create();

        $this->client->request('GET', '/applications/'.$application->id->value);

        self::assertSelectorTextContains('section[aria-labelledby="other-title"]', 'Data Engineer');
        self::assertSelectorTextContains('section[aria-labelledby="other-title"]', "same email address, which isn't verified");
        self::assertSelectorExists('section[aria-labelledby="other-title"] a[href="/applications/'.$other->id->value.'"]');
    }

    public function test_without_other_applications_there_is_no_such_section(): void
    {
        $application = JobApplicationFactory::createOne();

        $this->client->request('GET', '/applications/'.$application->id->value);

        self::assertSelectorNotExists('section[aria-labelledby="other-title"]');
    }

    public function test_a_failed_analysis_is_explained_without_technical_details(): void
    {
        $application = JobApplicationFactory::new()
            ->afterInstantiate(static fn ($a) => $a->failScreening('LLM timeout', new \DateTimeImmutable()))
            ->create();

        $this->client->request('GET', '/applications/'.$application->id->value);

        self::assertSelectorTextContains('main', "The AI analysis couldn't be completed.");
        self::assertSelectorTextNotContains('main', 'LLM timeout');
    }

    public function test_the_recruiter_moves_the_application_to_the_next_status(): void
    {
        $application = JobApplicationFactory::createOne();
        $crawler = $this->client->request('GET', '/applications/'.$application->id->value);

        self::assertSelectorTextContains('[aria-label="Hiring pipeline"] [aria-current="step"]', 'Received');
        // Advance, reject, and the reject confirmation revealed by JS.
        self::assertSame(['in_review', 'rejected', 'rejected'], $crawler->filter('section[aria-labelledby="status-title"] button[name="status"]')->extract(['value']));
        $this->client->submitForm('Move to in review');

        self::assertResponseRedirects('/applications/'.$application->id->value, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Status updated to "in review".');
        self::assertSelectorTextContains('[aria-label="Hiring pipeline"] [aria-current="step"]', 'In review');
    }

    public function test_the_recruiter_rejects_the_application(): void
    {
        $application = JobApplicationFactory::new()->inStatus(JobApplicationStatus::Interviewing)->create();
        $this->client->request('GET', '/applications/'.$application->id->value);

        // Without JS the "Reject" button submits directly; the inline confirmation is progressive enhancement.
        $this->client->submitForm('Reject');

        self::assertResponseRedirects('/applications/'.$application->id->value, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Status updated to "rejected".');
        self::assertSelectorTextContains('[aria-label="Hiring pipeline"] [aria-current="step"]', 'Rejected');
    }

    public function test_a_final_status_offers_no_further_changes(): void
    {
        $application = JobApplicationFactory::new()->inStatus(JobApplicationStatus::Hired)->create();

        $this->client->request('GET', '/applications/'.$application->id->value);

        self::assertSelectorTextContains('section[aria-labelledby="status-title"]', 'Final status');
        self::assertSelectorTextContains('[aria-label="Hiring pipeline"] [aria-current="step"]', 'Hired');
        self::assertSelectorNotExists('button[name="status"]');
    }

    public function test_a_forged_forbidden_transition_is_refused_by_the_domain(): void
    {
        $application = JobApplicationFactory::createOne();
        $crawler = $this->client->request('GET', '/applications/'.$application->id->value);

        // The buttons only offer allowed moves; a crafted request tries to skip steps.
        $form = $crawler->selectButton('Move to in review')->form();
        $this->client->request('POST', $form->getUri(), ['status' => 'hired', '_token' => $form->getValues()['_token']]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('[role="status"]', 'cannot move from "received" to "hired"');
        self::assertSelectorTextContains('[aria-label="Hiring pipeline"] [aria-current="step"]', 'Received');
    }

    public function test_changing_the_status_requires_a_valid_csrf_token(): void
    {
        $application = JobApplicationFactory::createOne();

        $this->client->request('POST', '/applications/'.$application->id->value.'/status', ['status' => 'in_review', '_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_an_unknown_application_is_not_found(): void
    {
        $this->client->request('GET', '/applications/0192f5a0-7c3b-7d2e-9a1b-3c4d5e6f7a8b');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/applications/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }
}
