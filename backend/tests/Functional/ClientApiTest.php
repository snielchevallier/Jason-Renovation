<?php

namespace App\Tests\Functional;

use App\Entity\Client;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\AuthenticatesApiClient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Premiere entite metier exposee en ecriture sur /api : on verifie le CRUD
 * complet, la protection de la ressource, la validation et l'ecriture de
 * l'adresse embarquee (le groupe adresse:write n'existait pas avant Client).
 */
final class ClientApiTest extends ApiTestCase
{
    use AuthenticatesApiClient;

    private const PAYLOAD = [
        'nom' => 'Dupont Maconnerie',
        'email' => 'contact@dupont-maconnerie.fr',
        'telephone' => '0102030405',
        'adresse' => [
            'ligne1' => '12 rue des Lilas',
            'codePostal' => '35000',
            'ville' => 'Rennes',
        ],
    ];

    public function testCollectionRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/api/clients');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateRequiresAuthentication(): void
    {
        static::createClient()->request('POST', '/api/clients', [
            'json' => self::PAYLOAD,
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthenticatedUserCanListClients(): void
    {
        $this->createAuthenticatedClient()->request('GET', '/api/clients');

        self::assertResponseIsSuccessful();
    }

    public function testCreateClientPersistsAddressAndTimestamps(): void
    {
        $response = $this->createAuthenticatedClient()->request('POST', '/api/clients', [
            'json' => self::PAYLOAD,
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = $response->toArray();
        self::assertSame('Dupont Maconnerie', $data['nom']);
        // L'adresse embarquee doit faire l'aller-retour : c'est tout l'objet
        // du groupe adresse:write ajoute sur l'embeddable.
        self::assertSame('12 rue des Lilas', $data['adresse']['ligne1']);
        self::assertSame('Rennes', $data['adresse']['ville']);
        self::assertSame('France', $data['adresse']['pays']);
        // Horodatage pose par TimestampableTrait, jamais par le client.
        self::assertArrayHasKey('createdAt', $data);
        self::assertArrayHasKey('updatedAt', $data);
    }

    public function testCreateClientWithoutNomIsRejected(): void
    {
        $this->createAuthenticatedClient()->request('POST', '/api/clients', [
            'json' => ['nom' => ''],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateClientWithInvalidEmailIsRejected(): void
    {
        $this->createAuthenticatedClient()->request('POST', '/api/clients', [
            'json' => ['nom' => 'Client', 'email' => 'pas-une-adresse'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testPatchUpdatesClient(): void
    {
        $client = $this->createAuthenticatedClient();
        $iri = $client->request('POST', '/api/clients', [
            'json' => self::PAYLOAD,
            'headers' => ['Content-Type' => 'application/ld+json'],
        ])->toArray()['@id'];

        $response = $client->request('PATCH', $iri, [
            'json' => ['telephone' => '0611223344'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('0611223344', $response->toArray()['telephone']);
    }

    public function testDeleteRemovesClient(): void
    {
        $client = $this->createAuthenticatedClient();
        $iri = $client->request('POST', '/api/clients', [
            'json' => self::PAYLOAD,
            'headers' => ['Content-Type' => 'application/ld+json'],
        ])->toArray()['@id'];

        $client->request('DELETE', $iri);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', $iri);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCollectionIsFilteredByPartialNom(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->createClientEntity('Dupont Maconnerie');
        $this->createClientEntity('Martin Couverture');

        $response = $client->request('GET', '/api/clients', ['query' => ['nom' => 'Dupont']]);

        self::assertResponseIsSuccessful();
        $data = $response->toArray();
        self::assertCount(1, $data['member']);
        self::assertSame('Dupont Maconnerie', $data['member'][0]['nom']);
    }

    private function createClientEntity(string $nom): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $entity = new Client();
        $entity->setNom($nom);

        $entityManager->persist($entity);
        $entityManager->flush();
    }
}
