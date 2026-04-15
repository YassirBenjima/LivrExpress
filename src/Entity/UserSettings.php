<?php

namespace App\Entity;

use App\Repository\UserSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSettingsRepository::class)]
#[ORM\Table(name: 'user_settings')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_SETTINGS_USER', fields: ['user'])]
class UserSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'json')]
    private array $parcelSettings = [];

    #[ORM\Column(type: 'json')]
    private array $packagingSettings = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getParcelSettings(): array
    {
        return $this->parcelSettings;
    }

    public function setParcelSettings(array $parcelSettings): static
    {
        $this->parcelSettings = $parcelSettings;

        return $this;
    }

    public function getPackagingSettings(): array
    {
        return $this->packagingSettings;
    }

    public function setPackagingSettings(array $packagingSettings): static
    {
        $this->packagingSettings = $packagingSettings;

        return $this;
    }
}

