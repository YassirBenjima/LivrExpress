<?php

namespace App\Entity;

use App\Repository\ColisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ColisRepository::class)]
class Colis
{
    public const TYPE_SIMPLE = 'Colis Simple';
    public const TYPE_STOCK = 'Colis du stock';
    public const ETAT_CREE = 'Créé';
    public const ETAT_EN_PREPARATION = 'En préparation';
    public const ETAT_EXPEDIE = 'Expédié';
    public const ETAT_LIVRE = 'Livré';
    public const ETAT_RETOUR = 'Retourné';
    public const STATUT_EN_ATTENTE = 'En attente';
    public const STATUT_EN_COURS = 'En cours';
    public const STATUT_REPORTE = 'Reporté';
    public const STATUT_ECHEC = 'Échec';
    public const STATUT_TERMINE = 'Terminé';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $orderNumber = null;

    #[ORM\Column(length: 30)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 30)]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $neighborhood = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 255)]
    private ?string $productNature = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recipient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $packageOption = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $replacePackage = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $oldOrderNumber = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $cartonOption = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $trackingCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $etat = self::ETAT_CREE;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->etat = self::ETAT_CREE;
        $this->statut = self::STATUT_EN_ATTENTE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $digitsOnly = preg_replace('/\D+/', '', $orderNumber) ?? '';
        $this->orderNumber = $digitsOnly !== '' ? 'CMD-' . $digitsOnly : null;
        $this->refreshTrackingCode();

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getNeighborhood(): ?string
    {
        return $this->neighborhood;
    }

    public function setNeighborhood(string $neighborhood): static
    {
        $this->neighborhood = $neighborhood;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getProductNature(): ?string
    {
        return $this->productNature;
    }

    public function setProductNature(string $productNature): static
    {
        $this->productNature = $productNature;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        $this->refreshTrackingCode();

        return $this;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(?string $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getPackageOption(): ?string
    {
        return $this->packageOption;
    }

    public function setPackageOption(?string $packageOption): static
    {
        $this->packageOption = $packageOption;

        return $this;
    }

    public function isReplacePackage(): bool
    {
        return $this->replacePackage;
    }

    public function setReplacePackage(bool $replacePackage): static
    {
        $this->replacePackage = $replacePackage;

        return $this;
    }

    public function getOldOrderNumber(): ?string
    {
        return $this->oldOrderNumber;
    }

    public function setOldOrderNumber(?string $oldOrderNumber): static
    {
        $this->oldOrderNumber = $oldOrderNumber;

        return $this;
    }

    public function getCartonOption(): ?string
    {
        return $this->cartonOption;
    }

    public function setCartonOption(?string $cartonOption): static
    {
        $this->cartonOption = $cartonOption;

        return $this;
    }

    public function getTrackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function setTrackingCode(?string $trackingCode): static
    {
        $this->trackingCode = $trackingCode;

        return $this;
    }

    private function refreshTrackingCode(): void
    {
        if (!$this->orderNumber || !$this->createdAt) {
            return;
        }

        $digitsOnly = preg_replace('/\D+/', '', $this->orderNumber) ?? '';
        if ($digitsOnly === '') {
            return;
        }

        $this->trackingCode = sprintf('F-%s-%s', $this->createdAt->format('Ymd'), $digitsOnly);
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): static
    {
        if ($etat === null || !\in_array($etat, self::getEtatsPossibles(), true)) {
            $this->etat = self::ETAT_CREE;

            return $this;
        }

        $this->etat = $etat;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        if ($statut === null || !\in_array($statut, self::getStatutsPossibles(), true)) {
            $this->statut = self::STATUT_EN_ATTENTE;

            return $this;
        }

        $this->statut = $statut;

        return $this;
    }

    /**
     * @return list<string>
     */
    public static function getEtatsPossibles(): array
    {
        return [
            self::ETAT_CREE,
            self::ETAT_EN_PREPARATION,
            self::ETAT_EXPEDIE,
            self::ETAT_LIVRE,
            self::ETAT_RETOUR,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getStatutsPossibles(): array
    {
        return [
            self::STATUT_EN_ATTENTE,
            self::STATUT_EN_COURS,
            self::STATUT_REPORTE,
            self::STATUT_ECHEC,
            self::STATUT_TERMINE,
        ];
    }
}
