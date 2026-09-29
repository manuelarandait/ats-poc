<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthCheckTest extends WebTestCase
{
    public function test_application_boots(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $client->getResponse()->getContent());
    }
}
