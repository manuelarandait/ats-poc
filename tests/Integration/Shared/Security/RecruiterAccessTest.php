<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Security;

use App\Tests\Recruitment\Factory\JobApplicationFactory;
use App\Tests\Recruitment\Factory\JobOfferFactory;
use App\Tests\Shared\Infrastructure\Security\RecruiterLogin;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Candidates use the public pages without an account; the recruiter area
 * (applications list, detail, status changes) requires signing in.
 */
final class RecruiterAccessTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function test_candidates_browse_and_apply_without_an_account(): void
    {
        $offer = JobOfferFactory::createOne();

        $this->client->request('GET', '/jobs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav', 'Recruiter login');
        self::assertSelectorTextNotContains('nav', 'Applications');

        $this->client->request('GET', '/jobs/'.$offer->id->value);
        self::assertResponseIsSuccessful();
    }

    #[DataProvider('recruiterPages')]
    public function test_the_recruiter_area_sends_anonymous_visitors_to_the_login(string $method, string $path): void
    {
        $application = JobApplicationFactory::createOne();

        $this->client->request($method, str_replace('{id}', $application->id->value, $path));

        self::assertResponseRedirects('/login');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function recruiterPages(): iterable
    {
        yield 'applications list' => ['GET', '/applications'];
        yield 'application detail' => ['GET', '/applications/{id}'];
        yield 'status change' => ['POST', '/applications/{id}/status'];
    }

    public function test_signing_in_shows_the_demo_account_and_goes_back_to_the_requested_page(): void
    {
        $this->client->request('GET', '/applications');
        $this->client->followRedirect();

        self::assertSelectorTextContains('main', 'Demo account: recruiter@ats.test / recruiter');

        $this->client->submitForm('Sign in', ['email' => RecruiterLogin::EMAIL, 'password' => RecruiterLogin::PASSWORD]);

        self::assertResponseRedirects('http://localhost/applications');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Applications');
        self::assertSelectorTextContains('nav', 'Log out');
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', ['email' => RecruiterLogin::EMAIL, 'password' => 'wrong']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('[role="alert"]', 'Invalid credentials.');

        $this->client->request('GET', '/applications');
        self::assertResponseRedirects('/login');
    }

    public function test_logging_out_closes_the_recruiter_area(): void
    {
        RecruiterLogin::as($this->client)->request('GET', '/applications');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Log out');
        self::assertResponseRedirects('/jobs');

        $this->client->request('GET', '/applications');
        self::assertResponseRedirects('/login');
    }
}
