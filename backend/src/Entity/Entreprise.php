<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Embeddable\Adresse;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Entreprise emettrice des devis. Table a ligne unique (singleton) : creee par
 * migration, editee via le back-office (EasyAdmin), lue via l'API pour l'entete
 * des devis.
 */
#[ORM\Entity(repositoryClass: EntrepriseRepository::class)]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['entreprise:read', 'adresse:read']],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: false,
)]
class Entreprise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['entreprise:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['entreprise:read'])]
    private ?string $nom = null;

    #[ORM\Column(length: 14, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $siret = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $numeroTvaIntracom = null;

    #[ORM\Embedded(class: Adresse::class)]
    #[Groups(['entreprise:read'])]
    private Adresse $adresse;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $mentionsLegales = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $conditionsPaiement = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $logo = null;

    #[ORM\Column(options: ['default' => 30])]
    #[Groups(['entreprise:read'])]
    private int $delaiValiditeDevisJours = 30;

    public function __construct()
    {
        $this->adresse = new Adresse();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getNumeroTvaIntracom(): ?string
    {
        return $this->numeroTvaIntracom;
    }

    public function setNumeroTvaIntracom(?string $numeroTvaIntracom): static
    {
        $this->numeroTvaIntracom = $numeroTvaIntracom;

        return $this;
    }

    public function getAdresse(): Adresse
    {
        return $this->adresse;
    }

    public function setAdresse(Adresse $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMentionsLegales(): ?string
    {
        return $this->mentionsLegales;
    }

    public function setMentionsLegales(?string $mentionsLegales): static
    {
        $this->mentionsLegales = $mentionsLegales;

        return $this;
    }

    public function getConditionsPaiement(): ?string
    {
        return $this->conditionsPaiement;
    }

    public function setConditionsPaiement(?string $conditionsPaiement): static
    {
        $this->conditionsPaiement = $conditionsPaiement;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getDelaiValiditeDevisJours(): int
    {
        return $this->delaiValiditeDevisJours;
    }

    public function setDelaiValiditeDevisJours(int $delaiValiditeDevisJours): static
    {
        $this->delaiValiditeDevisJours = $delaiValiditeDevisJours;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
