<?php

namespace App\Tests\Functional;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\CreatesUsers;

/**
 * Regression : flux d'authentification API (/api/login, deny-by-default,
 * anti brute-force). Comportements deja verifies manuellement en Phase 1,
 * desormais rejouables automatiquement.
 */
final class AuthenticationTest extends ApiTestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        // Le rate limiter (login_throttling) n'est pas dans la transaction
        // DAMA (cache fichier, pas la base) : on repart propre a chaque test.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testDocsAreAccessibleWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/api/docs.jsonld');

        self::assertResponseIsSuccessful();
    }

    public function testApiRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/api');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithValidCredentialsReturnsToken(): void
    {
        $this->createUser('valid@test.fr', 'MotDePasseValide1!');

        $response = static::createClient()->request('POST', '/api/login', [
            'json' => ['email' => 'valid@test.fr', 'password' => 'MotDePasseValide1!'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $response->toArray());
    }

    public function testLoginWithInvalidCredentialsIsRejected(): void
    {
        static::createClient()->request('POST', '/api/login', [
            'json' => ['email' => 'personne@test.fr', 'password' => 'incorrect'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginIsThrottledAfterTooManyFailedAttempts(): void
    {
        $client = static::createClient();
        $payload = ['json' => ['email' => 'throttle@test.fr', 'password' => 'incorrect']];

        for ($i = 0; $i < 5; ++$i) {
            $client->request('POST', '/api/login', $payload);
        }
        $client->request('POST', '/api/login', $payload);

        self::assertResponseStatusCodeSame(429);
    }
}
