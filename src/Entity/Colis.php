<?php

namespace App\Entity;

use App\Repository\ColisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ColisRepository::class)]
class Colis
{
    public const TYPE_SIMPLE = 'Colis Simple';
    public const TYPE_STOCK = 'Colis du stock';
    public const PAYMENT_COD = 'COD';
    public const PAYMENT_CRBT = 'CRBT';
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

    #[ORM\Column(options: ['default' => false])]
    private bool $fragile = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $allFragile = false;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $trackingCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $etat = self::ETAT_CREE;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20, options: ['default' => 'CRBT'])]
    private string $paymentType = self::PAYMENT_CRBT;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $deliveryFee = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->etat = self::ETAT_CREE;
        $this->statut = self::STATUT_EN_ATTENTE;
        $this->paymentType = self::PAYMENT_CRBT;
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

    public function isFragile(): bool
    {
        return $this->fragile;
    }

    public function setFragile(bool $fragile): static
    {
        $this->fragile = $fragile;

        return $this;
    }

    public function isAllFragile(): bool
    {
        return $this->allFragile;
    }

    public function setAllFragile(bool $allFragile): static
    {
        $this->allFragile = $allFragile;

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

    public function getEtatLabel(): string
    {
        return self::normalizeEtatLabel($this->etat);
    }

    public function getEtatBadgeClass(): string
    {
        return self::resolveEtatBadgeClass(self::normalizeEtatLabel($this->etat));
    }

    public function isRetourne(): bool
    {
        return self::normalizeEtatLabel($this->etat) === self::ETAT_RETOUR;
    }

    public function getPaymentType(): string
    {
        return $this->paymentType;
    }

    public function setPaymentType(string $paymentType): static
    {
        $normalized = strtoupper(trim($paymentType));
        $this->paymentType = \in_array($normalized, self::getPaymentTypesPossibles(), true)
            ? $normalized
            : self::PAYMENT_CRBT;

        return $this;
    }

    public function getDeliveryFee(): ?string
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(?string $deliveryFee): static
    {
        $this->deliveryFee = $deliveryFee;

        return $this;
    }

    public function isCodPayment(): bool
    {
        return \in_array(strtoupper($this->paymentType), self::getPaymentTypesPossibles(), true);
    }

    /**
     * @return list<string>
     */
    public static function getPaymentTypesPossibles(): array
    {
        return [
            self::PAYMENT_COD,
            self::PAYMENT_CRBT,
        ];
    }

    public static function normalizeEtatLabel(?string $etat): string
    {
        $etat = trim((string) ($etat ?? self::ETAT_CREE));

        return match ($etat) {
            'Cree' => self::ETAT_CREE,
            'En preparation' => self::ETAT_EN_PREPARATION,
            'Expedie' => self::ETAT_EXPEDIE,
            'Livre' => self::ETAT_LIVRE,
            'Retour' => self::ETAT_RETOUR,
            default => $etat !== '' ? $etat : self::ETAT_CREE,
        };
    }

    public static function resolveEtatBadgeClass(string $etatLabel): string
    {
        return match ($etatLabel) {
            self::ETAT_CREE => 'kt-badge-primary',
            self::ETAT_EN_PREPARATION => 'kt-badge-warning',
            self::ETAT_EXPEDIE => 'kt-badge-info',
            self::ETAT_LIVRE => 'kt-badge-success',
            self::ETAT_RETOUR => 'kt-badge-destructive',
            default => 'kt-badge-warning',
        };
    }

    public function getStatutLabel(): string
    {
        return self::normalizeStatutLabel($this->statut);
    }

    public function getStatutBadgeClass(): string
    {
        return self::resolveStatutBadgeClass(self::normalizeStatutLabel($this->statut));
    }

    public static function normalizeStatutLabel(?string $statut): string
    {
        $statut = trim((string) ($statut ?? self::STATUT_EN_ATTENTE));

        return match ($statut) {
            'Reporte' => self::STATUT_REPORTE,
            'Echec' => self::STATUT_ECHEC,
            'Termine' => self::STATUT_TERMINE,
            default => $statut !== '' ? $statut : self::STATUT_EN_ATTENTE,
        };
    }

    public static function resolveStatutBadgeClass(string $statutLabel): string
    {
        return match ($statutLabel) {
            self::STATUT_TERMINE => 'kt-badge-success',
            self::STATUT_EN_COURS => 'kt-badge-primary',
            self::STATUT_REPORTE => 'kt-badge-info',
            self::STATUT_ECHEC => 'kt-badge-destructive',
            default => 'kt-badge-warning',
        };
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
