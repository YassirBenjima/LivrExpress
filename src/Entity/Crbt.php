<?php

namespace App\Entity;

use App\Repository\CrbtRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrbtRepository::class)]
#[ORM\Table(name: 'crbt')]
#[ORM\Index(columns: ['status'], name: 'idx_crbt_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_crbt_created_at')]
#[ORM\UniqueConstraint(name: 'uniq_crbt_reference', columns: ['reference'])]
class Crbt
{
    public const STATUS_EN_ATTENTE = 'en_attente';
    public const STATUS_DISPONIBLE = 'disponible';
    public const STATUS_PAYE = 'paye';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $reference = null;

    #[ORM\OneToOne(targetEntity: Colis::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Colis $colis = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_EN_ATTENTE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $frais = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $montantFrais = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $montant = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $balance = '0.00';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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
        $now = $this->createdAt;
        $suffix = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->reference = sprintf('CRBT-%s-%s', $now->format('Ymd'), $suffix);

        return $this;
    }

    public function getColis(): ?Colis
    {
        return $this->colis;
    }

    public function setColis(Colis $colis): static
    {
        $this->colis = $colis;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!\in_array($status, self::getStatusesPossibles(), true)) {
            $status = self::STATUS_EN_ATTENTE;
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

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getFrais(): string
    {
        return $this->frais;
    }

    public function setFrais(string $frais): static
    {
        $this->frais = $frais;

        return $this;
    }

    public function getMontantFrais(): string
    {
        return $this->montantFrais;
    }

    public function setMontantFrais(string $montantFrais): static
    {
        $this->montantFrais = $montantFrais;

        return $this;
    }

    public function getMontant(): string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    /**
     * @return list<string>
     */
    public static function getStatusesPossibles(): array
    {
        return [
            self::STATUS_EN_ATTENTE,
            self::STATUS_DISPONIBLE,
            self::STATUS_PAYE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_EN_ATTENTE => 'En attente',
            self::STATUS_DISPONIBLE => 'Disponible',
            self::STATUS_PAYE => 'Payé',
        ];
    }
}
