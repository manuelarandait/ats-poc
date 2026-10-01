<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Security;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Authenticates the test client as the demo recruiter without going through
 * the login form (that one is covered by RecruiterAccessTest).
 *
 * The user is loaded from the real provider: Symfony refreshes the user on
 * every request and logs out a session whose user "changed" (e.g. a
 * hand-built user without the configured password hash).
 */
final class RecruiterLogin
{
    public const string EMAIL = 'recruiter@ats.test';
    public const string PASSWORD = 'recruiter';

    public static function as(KernelBrowser $client): KernelBrowser
    {
        // KernelBrowser::getContainer() is the test container, which exposes private services.
        $provider = $client->getContainer()->get('security.user.provider.concrete.recruiters'); // @phpstan-ignore symfonyContainer.privateService

        return $client->loginUser($provider->loadUserByIdentifier(self::EMAIL));
    }
}
