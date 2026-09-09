<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\UniteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Unites de mesure des lignes de devis (m2, ml, u...). Table de reference :
 * lecture seule via l'API, gestion via le back-office (EasyAdmin).
 */
#[ORM\Entity(repositoryClass: UniteRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_UNITE_CODE', fields: ['code'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['unite:read']],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: false,
    order: ['position' => 'ASC', 'code' => 'ASC'],
)]
class Unite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['unite:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    #[Groups(['unite:read'])]
    private ?string $code = null;

    #[ORM\Column(length: 50)]
    #[Groups(['unite:read'])]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Groups(['unite:read'])]
    private bool $actif = true;

    #[ORM\Column]
    #[Groups(['unite:read'])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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
        return $this->code ?? '';
    }
}
