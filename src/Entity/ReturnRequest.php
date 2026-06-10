<?php

namespace App\Entity;

use App\Repository\ReturnRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReturnRequestRepository::class)]
#[ORM\Table(name: 'return_request')]
#[ORM\Index(columns: ['status'], name: 'idx_return_request_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_return_request_created_at')]
class ReturnRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $receptionType = 'En Agence';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $bonReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, Colis> */
    #[ORM\ManyToMany(targetEntity: Colis::class)]
    #[ORM\JoinTable(name: 'return_request_colis')]
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

    public function getReceptionType(): string
    {
        return $this->receptionType;
    }

    public function setReceptionType(string $receptionType): static
    {
        $this->receptionType = trim($receptionType);

        return $this;
    }

    public function getBonReference(): ?string
    {
        return $this->bonReference;
    }

    public function setBonReference(?string $bonReference): static
    {
        $this->bonReference = $bonReference;

        return $this;
    }

    public function generateBonReference(): static
    {
        $now = $this->createdAt;
        $suffix = $now->format('His') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        $this->bonReference = sprintf('BR-%s-%s', $now->format('dmy'), $suffix);

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $note = $note !== null ? trim($note) : null;
        $this->note = $note !== '' ? $note : null;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!\in_array($status, self::getStatusesPossibles(), true)) {
            $status = self::STATUS_PENDING;
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

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

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
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_RECEIVED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_PROCESSING => 'En traitement',
            self::STATUS_RECEIVED => 'Reçu',
            self::STATUS_CANCELLED => 'Annulé',
        ];
    }
}
