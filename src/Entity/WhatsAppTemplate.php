<?php

namespace App\Entity;

use App\Repository\WhatsAppTemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WhatsAppTemplateRepository::class)]
#[ORM\Table(name: 'whatsapp_template')]
#[ORM\Index(columns: ['status'], name: 'idx_whatsapp_template_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_whatsapp_template_created_at')]
#[ORM\HasLifecycleCallbacks]
class WhatsAppTemplate
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const ALLOWED_PLACEHOLDERS = [
        '@name',
        '@product',
        '@address',
        '@numLivreur',
        '@numClient',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.'
    )]
    #[ORM\Column(type: 'string', length: 150)]
    private string $title = '';

    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Le message ne doit pas dépasser {{ limit }} caractères.'
    )]
    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[Assert\Choice(
        choices: [self::STATUS_ACTIVE, self::STATUS_INACTIVE],
        message: 'Statut invalide.'
    )]
    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_ACTIVE])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = trim($message);

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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

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
