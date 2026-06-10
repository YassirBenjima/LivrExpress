<?php

namespace App\Entity;

use App\Repository\BonLivraisonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BonLivraisonRepository::class)]
#[ORM\Table(name: 'bon_livraison')]
#[ORM\Index(columns: ['status'], name: 'idx_bon_livraison_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_bon_livraison_created_at')]
#[ORM\UniqueConstraint(name: 'uniq_bon_livraison_reference', columns: ['reference'])]
class BonLivraison
{
    public const STATUS_BROUILLON = 'brouillon';
    public const STATUS_ENREGISTRE = 'enregistre';
    public const STATUS_ANNULE = 'annule';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $reference = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_BROUILLON;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $registeredAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, Colis> */
    #[ORM\ManyToMany(targetEntity: Colis::class)]
    #[ORM\JoinTable(name: 'bon_livraison_colis')]
    private Collection $colis;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->colis = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function generateReference(): static
    {
        $now = $this->createdAt ?? new \DateTimeImmutable();
        $suffix = $now->format('His') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        $this->reference = sprintf('BL-%s-%s', $now->format('dmy'), $suffix);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!\in_array($status, self::getStatusesPossibles(), true)) {
            $status = self::STATUS_BROUILLON;
        }

        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRegisteredAt(): ?\DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(?\DateTimeImmutable $registeredAt): static
    {
        $this->registeredAt = $registeredAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /** @return Collection<int, Colis> */
    public function getColis(): Collection
    {
        return $this->colis;
    }

    public function addColis(Colis $colis): static
    {
        if (!$this->colis->contains($colis)) {
            $this->colis->add($colis);
        }

        return $this;
    }

    public function removeColis(Colis $colis): static
    {
        $this->colis->removeElement($colis);

        return $this;
    }

    public function clearColis(): static
    {
        $this->colis->clear();

        return $this;
    }

    /**
     * @return list<string>
     */
    public static function getStatusesPossibles(): array
    {
        return [
            self::STATUS_BROUILLON,
            self::STATUS_ENREGISTRE,
            self::STATUS_ANNULE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_BROUILLON => 'Brouillon',
            self::STATUS_ENREGISTRE => 'Enregistré',
            self::STATUS_ANNULE => 'Annulé',
        ];
    }
}
