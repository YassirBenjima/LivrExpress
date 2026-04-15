<?php

namespace App\Entity;

use App\Repository\PickupRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PickupRequestRepository::class)]
#[ORM\Table(name: 'pickup_request')]
#[ORM\Index(columns: ['status'], name: 'idx_pickup_request_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_pickup_request_created_at')]
#[ORM\UniqueConstraint(name: 'uniq_pickup_request_product_status', columns: ['product_id', 'status'])]
class PickupRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StockProduct::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StockProduct $product = null;

    #[ORM\Column(length: 255)]
    private string $productNameSnapshot = '';

    #[ORM\Column(length: 255)]
    private string $city = '';

    #[ORM\Column(length: 255)]
    private string $neighborhood = '';

    #[ORM\Column(length: 255)]
    private string $address = '';

    #[ORM\Column(length: 50)]
    private string $phone = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $supplierPhone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private bool $hasLabels = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(length: 30, options: ['simple' => 'stock'])]
    private string $type = 'stock';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $assignedDriver = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?StockProduct
    {
        return $this->product;
    }

    public function setProduct(StockProduct $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getProductNameSnapshot(): string
    {
        return $this->productNameSnapshot;
    }

    public function setProductNameSnapshot(string $productNameSnapshot): self
    {
        $this->productNameSnapshot = trim($productNameSnapshot);

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = trim($city);

        return $this;
    }

    public function getNeighborhood(): string
    {
        return $this->neighborhood;
    }

    public function setNeighborhood(string $neighborhood): self
    {
        $this->neighborhood = trim($neighborhood);

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = trim($address);

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = trim($phone);

        return $this;
    }

    public function getSupplierPhone(): ?string
    {
        return $this->supplierPhone;
    }

    public function setSupplierPhone(?string $supplierPhone): self
    {
        $supplierPhone = $supplierPhone !== null ? trim($supplierPhone) : null;
        $this->supplierPhone = $supplierPhone !== '' ? $supplierPhone : null;

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

        return $this;
    }

    public function hasLabels(): bool
    {
        return $this->hasLabels;
    }

    public function setHasLabels(bool $hasLabels): self
    {
        $this->hasLabels = $hasLabels;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = trim($status);

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = trim($type);

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getAssignedDriver(): ?string
    {
        return $this->assignedDriver;
    }

    public function setAssignedDriver(?string $assignedDriver): self
    {
        $assignedDriver = $assignedDriver !== null ? trim($assignedDriver) : null;
        $this->assignedDriver = $assignedDriver !== '' ? $assignedDriver : null;

        return $this;
    }
}
