<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\CreatesUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Regression : garde-fous anti-lockout du back-office (UserCrudController).
 *
 * Le cas de suppression n'avait jamais pu etre verifie manuellement : le
 * bouton "Supprimer" d'EasyAdmin recupere son jeton CSRF en JavaScript au
 * clic, ce que `curl` ne peut pas simuler. Le client de test Symfony n'a pas
 * ce probleme : on peut generer un jeton valide directement via le service
 * du conteneur, dans la meme session que le client.
 *
 * Les garde-fous levent AdminGuardException, transformee par
 * AdminGuardExceptionListener en redirection + message flash (jamais un 500).
 */
final class UserCrudGuardTest extends WebTestCase
{
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testAdminCannotRemoveOwnAdminRole(): void
    {
        $admin = $this->createUser('self-demote@test.fr', 'MotDePasseValide1!', ['ROLE_ADMIN']);
        $this->logIn('self-demote@test.fr', 'MotDePasseValide1!');

        $crawler = $this->client->request('GET', \sprintf('/admin/user/%d/edit', $admin->getId()));
        $form = $crawler->filter('form[name="User"]')->form();
        $form['User[roles][0]']->untick(); // decoche ROLE_ADMIN (index 0), ROLE_USER (index 1) reste coche
        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertUserStillHasRole($admin->getId(), 'ROLE_ADMIN');
        $this->assertFlashMessageContains('Impossible de retirer votre propre role administrateur.');
    }

    public function testAdminCannotDeleteOwnAccount(): void
    {
        $admin = $this->createUser('self-delete@test.fr', 'MotDePasseValide1!', ['ROLE_ADMIN']);
        $this->logIn('self-delete@test.fr', 'MotDePasseValide1!');

        $this->delete($admin->getId());

        self::assertResponseRedirects();
        self::assertUserExists($admin->getId());
        $this->assertFlashMessageContains('Impossible de supprimer votre propre compte.');
    }

    public function testAdminCanDemoteAnotherAdminIfNotTheLastOne(): void
    {
        $acting = $this->createUser('acting-admin@test.fr', 'MotDePasseValide1!', ['ROLE_ADMIN']);
        $other = $this->createUser('other-admin@test.fr', 'MotDePasseValide1!', ['ROLE_ADMIN']);
        $this->logIn('acting-admin@test.fr', 'MotDePasseValide1!');

        $crawler = $this->client->request('GET', \sprintf('/admin/user/%d/edit', $other->getId()));
        $form = $crawler->filter('form[name="User"]')->form();
        $form['User[roles][0]']->untick(); // decoche ROLE_ADMIN (index 0), ROLE_USER (index 1) reste coche
        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertUserStillHasRole($acting->getId(), 'ROLE_ADMIN');
        self::assertUserDoesNotHaveRole($other->getId(), 'ROLE_ADMIN');
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

    /**
     * Le bouton "Supprimer" d'EasyAdmin recupere son jeton CSRF en JavaScript
     * au clic (rien dans le HTML statique) : on genere un jeton valide pour
     * la session courante en repoussant temporairement la derniere requete
     * sur la pile (le gestionnaire de jetons a besoin d'une requete "courante"
     * pour retrouver la session).
     */
    private function delete(int $userId): void
    {
        $this->client->request('GET', '/admin');

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($this->client->getRequest());
        try {
            $token = static::getContainer()
                ->get(CsrfTokenManagerInterface::class)
                ->getToken('ea-delete')
                ->getValue();
            $this->client->getRequest()->getSession()->save();
        } finally {
            $requestStack->pop();
        }

        $this->client->request('POST', \sprintf('/admin/user/%d/delete', $userId), ['token' => $token]);
    }

    private function assertFlashMessageContains(string $needle): void
    {
        $this->client->followRedirect();
        self::assertStringContainsString($needle, (string) $this->client->getResponse()->getContent());
    }

    private static function assertUserExists(int $userId): void
    {
        self::assertNotNull(self::userRepository()->find($userId), 'l\'utilisateur ne devrait pas avoir ete supprime');
    }

    private static function assertUserStillHasRole(int $userId, string $role): void
    {
        $user = self::userRepository()->find($userId);
        self::assertNotNull($user);
        self::assertContains($role, $user->getRoles());
    }

    private static function assertUserDoesNotHaveRole(int $userId, string $role): void
    {
        $user = self::userRepository()->find($userId);
        self::assertNotNull($user);
        self::assertNotContains($role, $user->getRoles());
    }

    private static function userRepository(): \App\Repository\UserRepository
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        // Vide la carte d'identite : sans ca, find() renvoie l'entite deja en
        // memoire (mutee par le formulaire, jamais flushee car le garde-fou a
        // bloque avant), pas l'etat reellement persiste en base.
        $entityManager->clear();

        return $entityManager->getRepository(User::class);
    }
}
