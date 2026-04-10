<?php

namespace App\Entity;

use App\Repository\StockProductVariantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockProductVariantRepository::class)]
#[ORM\Table(name: 'stock_product_variant')]
#[ORM\Index(columns: ['barcode'], name: 'idx_stock_product_variant_barcode')]
class StockProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StockProduct::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StockProduct $product = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column]
    private int $quantity;

    public function __construct(string $name, int $quantity)
    {
        $this->name = $name;
        $this->quantity = $quantity;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?StockProduct
    {
        return $this->product;
    }

    public function setProduct(?StockProduct $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): self
    {
        $barcode = $barcode !== null ? trim($barcode) : null;
        $this->barcode = $barcode !== '' ? $barcode : null;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }
}

