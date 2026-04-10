<?php

namespace App\Entity;

use App\Repository\StockProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockProductRepository::class)]
#[ORM\Table(name: 'stock_product')]
#[ORM\Index(columns: ['name'], name: 'idx_stock_product_name')]
#[ORM\Index(columns: ['category'], name: 'idx_stock_product_category')]
class StockProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $category;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoPath = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, StockProductVariant>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: StockProductVariant::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $variants;

    public function __construct(string $name, string $category)
    {
        $this->name = $name;
        $this->category = $category;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->variants = new ArrayCollection();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        $this->touch();

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
        $this->touch();

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;
        $this->touch();

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $note = $note !== null ? trim($note) : null;
        $this->note = $note !== '' ? $note : null;
        $this->touch();

        return $this;
    }

    public function getPhotoPath(): ?string
    {
        return $this->photoPath;
    }

    public function setPhotoPath(?string $photoPath): self
    {
        $photoPath = $photoPath !== null ? trim($photoPath) : null;
        $this->photoPath = $photoPath !== '' ? $photoPath : null;
        $this->touch();

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
     * @return Collection<int, StockProductVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(StockProductVariant $variant): self
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
            $this->touch();
        }

        return $this;
    }

    public function removeVariant(StockProductVariant $variant): self
    {
        if ($this->variants->removeElement($variant)) {
            $variant->setProduct(null);
            $this->touch();
        }

        return $this;
    }
}

