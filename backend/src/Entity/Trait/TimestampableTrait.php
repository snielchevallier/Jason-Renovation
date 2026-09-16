<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Horodatage createdAt/updatedAt, gere automatiquement par Doctrine
 * (aucun champ a remplir a la creation ou a l'edition).
 *
 * IMPORTANT : l'entite qui utilise ce trait doit porter l'attribut
 * #[ORM\HasLifecycleCallbacks] — Doctrine ignore sinon silencieusement les
 * callbacks PrePersist/PreUpdate (erreur en base : createdAt/updatedAt NULL).
 */
trait TimestampableTrait
{
    #[ORM\Column]
    #[Groups(['timestamps:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['timestamps:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersistTimestamps(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
