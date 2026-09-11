<?php

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Cree des utilisateurs directement en base pour les tests (persiste, sans
 * passer par le back-office). Chaque test tourne dans une transaction DAMA
 * annulee a la fin : aucun nettoyage manuel necessaire.
 */
trait CreatesUsers
{
    private function createUser(string $email, string $password, array $roles = ['ROLE_USER'], string $nom = 'Test'): User
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setRoles($roles);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
