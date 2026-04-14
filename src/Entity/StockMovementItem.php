<?php

namespace App\Entity;

use App\Repository\StockMovementItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMovementItemRepository::class)]
#[ORM\Table(name: 'stock_movement_item')]
class StockMovementItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StockMovement::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StockMovement $movement = null;

    #[ORM\ManyToOne(targetEntity: StockProductVariant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?StockProductVariant $variant = null;

    #[ORM\Column(type: 'integer')]
    private int $quantity = 0;

    public function __construct(StockProductVariant $variant, int $quantity)
    {
        $this->variant = $variant;
        $this->quantity = max(0, $quantity);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMovement(): ?StockMovement
    {
        return $this->movement;
    }

    public function setMovement(?StockMovement $movement): self
    {
        $this->movement = $movement;

        return $this;
    }

    public function getVariant(): ?StockProductVariant
    {
        return $this->variant;
    }

    public function setVariant(StockProductVariant $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = max(0, $quantity);

        return $this;
    }
}

