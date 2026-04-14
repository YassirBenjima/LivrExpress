<?php

namespace App\Entity;

use App\Repository\StockMovementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Table(name: 'stock_movement')]
#[ORM\UniqueConstraint(name: 'uniq_stock_movement_reference', columns: ['reference'])]
#[ORM\HasLifecycleCallbacks]
class StockMovement
{
    public const DIRECTION_ENTRY = 'entry';
    public const DIRECTION_EXIT = 'exit';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 30)]
    private string $direction = self::DIRECTION_ENTRY;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $reference;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, StockMovementItem>
     */
    #[ORM\OneToMany(mappedBy: 'movement', targetEntity: StockMovementItem::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    public function __construct(string $reference)
    {
        $this->reference = $reference;
        $this->items = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): self
    {
        $this->direction = $direction;

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, StockMovementItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(StockMovementItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setMovement($this);
        }

        return $this;
    }

    public function removeItem(StockMovementItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getMovement() === $this) {
                $item->setMovement(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

