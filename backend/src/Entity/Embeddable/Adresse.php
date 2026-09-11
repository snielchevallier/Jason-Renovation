<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Adresse postale reutilisable (Entreprise, puis Client et Chantier).
 * Objet embarque : ses colonnes vivent dans la table de l'entite porteuse
 * (prefixe "adresse_").
 */
#[ORM\Embeddable]
class Adresse
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['adresse:read'])]
    private ?string $ligne1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['adresse:read'])]
    private ?string $ligne2 = null;

    #[ORM\Column(length: 16, nullable: true)]
    #[Groups(['adresse:read'])]
    private ?string $codePostal = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['adresse:read'])]
    private ?string $ville = null;

    #[ORM\Column(length: 100, nullable: true, options: ['default' => 'France'])]
    #[Groups(['adresse:read'])]
    private ?string $pays = 'France';

    public function getLigne1(): ?string
    {
        return $this->ligne1;
    }

    public function setLigne1(?string $ligne1): static
    {
        $this->ligne1 = $ligne1;

        return $this;
    }

    public function getLigne2(): ?string
    {
        return $this->ligne2;
    }

    public function setLigne2(?string $ligne2): static
    {
        $this->ligne2 = $ligne2;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function isVide(): bool
    {
        return null === $this->ligne1
            && null === $this->ligne2
            && null === $this->codePostal
            && null === $this->ville;
    }

    public function __toString(): string
    {
        $lignes = array_filter([
            $this->ligne1,
            $this->ligne2,
            trim(($this->codePostal ?? '').' '.($this->ville ?? '')) ?: null,
            $this->pays,
        ]);

        return implode(', ', $lignes);
    }
}
