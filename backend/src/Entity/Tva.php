<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TvaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Taux de TVA applicables sur les lignes de devis. Table de reference :
 * lecture seule via l'API, gestion via le back-office (EasyAdmin).
 */
#[ORM\Entity(repositoryClass: TvaRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_TVA_TAUX', fields: ['taux'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['tva:read']],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: false,
    order: ['position' => 'ASC', 'taux' => 'ASC'],
)]
class Tva
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['tva:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Groups(['tva:read'])]
    private ?string $taux = null;

    #[ORM\Column(length: 100)]
    #[Groups(['tva:read'])]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Groups(['tva:read'])]
    private bool $actif = true;

    #[ORM\Column]
    #[Groups(['tva:read'])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaux(): ?string
    {
        return $this->taux;
    }

    public function setTaux(string $taux): static
    {
        $this->taux = $taux;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function __toString(): string
    {
        $taux = rtrim(rtrim($this->taux ?? '0', '0'), '.');

        return $taux.' %';
    }
}
