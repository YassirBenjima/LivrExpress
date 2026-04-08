<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(length: 255)]
    private ?string $businessName = null;

    #[ORM\Column(length: 255)]
    private ?string $businessPhone = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ice = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rc = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $labelMessage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $packageOption = 'Ne pas ouvrir le colis';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bankName = null;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $bankRib = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $returnReception = 'En Agence';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $returnAgency = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $returnPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $returnCity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $returnNeighborhood = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getBusinessName(): ?string
    {
        return $this->businessName;
    }

    public function setBusinessName(string $businessName): static
    {
        $this->businessName = $businessName;

        return $this;
    }

    public function getBusinessPhone(): ?string
    {
        return $this->businessPhone;
    }

    public function setBusinessPhone(string $businessPhone): static
    {
        $this->businessPhone = $businessPhone;

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

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getClientType(): ?string
    {
        return $this->clientType;
    }

    public function setClientType(?string $clientType): static
    {
        $this->clientType = $clientType;

        return $this;
    }

    public function getIce(): ?string
    {
        return $this->ice;
    }

    public function setIce(?string $ice): static
    {
        $this->ice = $ice;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getRc(): ?string
    {
        return $this->rc;
    }

    public function setRc(?string $rc): static
    {
        $this->rc = $rc;

        return $this;
    }

    public function getLabelMessage(): ?string
    {
        return $this->labelMessage;
    }

    public function setLabelMessage(?string $labelMessage): static
    {
        $this->labelMessage = $labelMessage;

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

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getBankRib(): ?string
    {
        return $this->bankRib;
    }

    public function setBankRib(?string $bankRib): static
    {
        $this->bankRib = $bankRib;

        return $this;
    }

    public function getReturnReception(): ?string
    {
        return $this->returnReception;
    }

    public function setReturnReception(?string $returnReception): static
    {
        $this->returnReception = $returnReception;

        return $this;
    }

    public function getReturnAgency(): ?string
    {
        return $this->returnAgency;
    }

    public function setReturnAgency(?string $returnAgency): static
    {
        $this->returnAgency = $returnAgency;

        return $this;
    }

    public function getReturnPhone(): ?string
    {
        return $this->returnPhone;
    }

    public function setReturnPhone(?string $returnPhone): static
    {
        $this->returnPhone = $returnPhone;

        return $this;
    }

    public function getReturnCity(): ?string
    {
        return $this->returnCity;
    }

    public function setReturnCity(?string $returnCity): static
    {
        $this->returnCity = $returnCity;

        return $this;
    }

    public function getReturnNeighborhood(): ?string
    {
        return $this->returnNeighborhood;
    }

    public function setReturnNeighborhood(?string $returnNeighborhood): static
    {
        $this->returnNeighborhood = $returnNeighborhood;

        return $this;
    }
}
