<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Horodate la derniere connexion au back-office (firewall "admin", session).
 *
 * Le firewall "api" (JWT) re-authentifie a chaque requete : y brancher ce
 * listener ecrirait en base a chaque appel API, ce qui n'a pas de sens pour
 * une "derniere connexion" et couterait une requete d'ecriture par appel.
 */
#[AsEventListener]
class LastLoginListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        if ('admin' !== $event->getFirewallName()) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
