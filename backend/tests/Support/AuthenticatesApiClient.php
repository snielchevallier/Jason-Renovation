<?php

namespace App\Tests\Support;

use ApiPlatform\Symfony\Bundle\Test\Client;

/**
 * Fabrique un client de test deja porteur d'un JWT valide, pour tous les
 * endpoints /api proteges. Complete CreatesUsers, qui cree le compte en base.
 */
trait AuthenticatesApiClient
{
    use CreatesUsers;

    private function createAuthenticatedClient(string $email = 'api@test.fr', array $roles = ['ROLE_USER']): Client
    {
        $password = 'MotDePasseValide1!';
        $this->createUser($email, $password, $roles);

        $client = static::createClient();
        $token = $client->request('POST', '/api/login', [
            'json' => ['email' => $email, 'password' => $password],
        ])->toArray()['token'];

        $client->setDefaultOptions(['auth_bearer' => $token]);

        return $client;
    }
}
