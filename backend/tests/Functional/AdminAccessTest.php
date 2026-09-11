<?php

namespace App\Tests\Functional;

use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression : acces au back-office (firewall "admin", session, ROLE_ADMIN).
 */
final class AdminAccessTest extends WebTestCase
{
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        // createClient() doit demarrer le noyau lui-meme : on ne peut pas
        // appeler getContainer() avant, sous peine de LogicException.
        $this->client = static::createClient();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testLoginFormIsPubliclyAccessible(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }

    public function testAdminCanLogInAndReachDashboard(): void
    {
        $this->createUser('admin-access@test.fr', 'MotDePasseValide1!', ['ROLE_ADMIN']);

        $this->logIn('admin-access@test.fr', 'MotDePasseValide1!');

        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testNonAdminIsForbiddenFromAdmin(): void
    {
        $this->createUser('staff-access@test.fr', 'MotDePasseValide1!', ['ROLE_USER']);

        $this->logIn('staff-access@test.fr', 'MotDePasseValide1!');
        $this->client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    private function logIn(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => $email,
            '_password' => $password,
        ]);
        $this->client->submit($form);
    }
}
