<?php

namespace App\Service;

use App\Entity\BonLivraison;
use App\Entity\City;
use App\Entity\Colis;
use App\Entity\Crbt;
use App\Entity\PickupRequest;
use App\Entity\ReturnRequest;
use App\Entity\StockMovement;
use App\Entity\StockMovementItem;
use App\Entity\StockProduct;
use App\Entity\StockProductVariant;
use App\Entity\User;
use App\Entity\UserSettings;
use App\Entity\WhatsAppTemplate;
use App\Repository\CityRepository;
use App\Repository\ColisRepository;
use App\Repository\CrbtRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TestDataSeeder
{
    public const CLIENT_EMAIL = 'test.client@livrexp.test';
    public const STAFF_EMAIL = 'test.superviseur@livrexp.test';
    public const DEFAULT_PASSWORD = 'Test1234!';

    private const COLIS_ORDER_PREFIX = '900';
    private const STOCK_BARCODE_PREFIX = 'SEED-';
    private const MOVEMENT_PREFIX = 'SEED-';
    private const WHATSAPP_TITLE_PREFIX = 'Demo ';
    private const PICKUP_NOTE_PREFIX = 'Demo ramassage ';
    private const RETURN_NOTE_PREFIX = 'Demo retour ';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly CityRepository $cityRepository,
        private readonly ColisRepository $colisRepository,
        private readonly CrbtRepository $crbtRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function isSeeded(): bool
    {
        return $this->userRepository->findOneBy(['email' => self::CLIENT_EMAIL]) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function seed(): array
    {
        $city = $this->resolveCity();
        $client = $this->createClientUser($city);
        $staff = $this->createStaffUser($city);
        $this->entityManager->flush();

        $colisMap = $this->seedColis($city);
        $this->entityManager->flush();

        $this->seedCrbtPaye($colisMap['livre_cod']);
        $pickups = $this->seedPickupRequests($client, $city);
        $bons = $this->seedBonLivraison($client, $colisMap);
        $returns = $this->seedReturnRequests($client, $colisMap);
        $stock = $this->seedStock();
        $templates = $this->seedWhatsAppTemplates();

        $this->entityManager->flush();

        return [
            'users' => [
                'client' => ['email' => self::CLIENT_EMAIL, 'password' => self::DEFAULT_PASSWORD],
                'staff' => ['email' => self::STAFF_EMAIL, 'password' => self::DEFAULT_PASSWORD],
            ],
            'city' => $city,
            'colis' => \count($colisMap),
            'pickups' => \count($pickups),
            'bons' => \count($bons),
            'returns' => \count($returns),
            'stock' => $stock,
            'whatsapp_templates' => \count($templates),
        ];
    }

    public function purge(): void
    {
        $em = $this->entityManager;
        $client = $this->userRepository->findOneBy(['email' => self::CLIENT_EMAIL]);

        foreach ($em->getRepository(ReturnRequest::class)->findAll() as $request) {
            if ($this->isTestReturnRequest($request, $client)) {
                $em->remove($request);
            }
        }

        foreach ($em->getRepository(BonLivraison::class)->findAll() as $bon) {
            if ($this->isTestBonLivraison($bon)) {
                $em->remove($bon);
            }
        }

        foreach ($em->getRepository(PickupRequest::class)->findAll() as $pickup) {
            if ($this->isTestPickup($pickup, $client)) {
                $em->remove($pickup);
            }
        }

        foreach ($this->findTestColis() as $colis) {
            $em->remove($colis);
        }

        foreach ($em->getRepository(StockMovement::class)->findAll() as $movement) {
            if (str_starts_with($movement->getReference(), self::MOVEMENT_PREFIX)) {
                $em->remove($movement);
            }
        }

        foreach ($em->getRepository(StockProduct::class)->findAll() as $product) {
            $barcode = $product->getBarcode() ?? '';
            if (str_starts_with($barcode, self::STOCK_BARCODE_PREFIX)) {
                $em->remove($product);
            }
        }

        foreach ($em->getRepository(WhatsAppTemplate::class)->findAll() as $template) {
            if (str_starts_with($template->getTitle(), self::WHATSAPP_TITLE_PREFIX)) {
                $em->remove($template);
            }
        }

        foreach ([self::CLIENT_EMAIL, self::STAFF_EMAIL] as $email) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user === null) {
                continue;
            }

            $settings = $em->getRepository(UserSettings::class)->findOneBy(['user' => $user]);
            if ($settings !== null) {
                $em->remove($settings);
            }

            $em->remove($user);
        }

        $em->flush();
    }

    private function resolveCity(): string
    {
        $city = $this->cityRepository->findOneBy([], ['id' => 'ASC']);
        if ($city instanceof City && $city->getName() !== null && $city->getName() !== '') {
            return $city->getName();
        }

        return 'Casablanca';
    }

    private function createClientUser(string $city): User
    {
        $existing = $this->userRepository->findOneBy(['email' => self::CLIENT_EMAIL]);
        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User();
        $user->setEmail(self::CLIENT_EMAIL);
        $user->setRoles(['ROLE_CLIENT']);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));
        $user->setFullName('Client Test LivrExpress');
        $user->setBusinessName('Boutique Test SEED');
        $user->setBusinessPhone('0612345678');
        $user->setCity($city);
        $user->setAddress('12 Rue Test, Quartier Seed');
        $user->setClientType('E-commerce');
        $user->setIce('002345678000012');
        $user->setBankName('CIH Bank');
        $user->setBankRib('230450000000000000000000');
        $user->setReturnReception('En Agence');
        $user->setReturnAgency('Agence Casablanca Centre');
        $user->setReturnPhone('0698765432');
        $user->setReturnCity($city);
        $user->setReturnNeighborhood('Maarif');

        $settings = new UserSettings();
        $settings->setUser($user);
        $settings->setParcelSettings([
            'defaultCity' => $city,
            'defaultPackageOption' => 'Ne pas ouvrir le colis',
        ]);
        $settings->setPackagingSettings([
            'cartonDefault' => 'm',
            'fragileDefault' => false,
        ]);

        $this->entityManager->persist($user);
        $this->entityManager->persist($settings);

        return $user;
    }

    private function createStaffUser(string $city): User
    {
        $existing = $this->userRepository->findOneBy(['email' => self::STAFF_EMAIL]);
        if ($existing instanceof User) {
            return $existing;
        }

        $user = new User();
        $user->setEmail(self::STAFF_EMAIL);
        $user->setRoles(['ROLE_SUPERVISEUR']);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));
        $user->setFullName('Superviseur Test');
        $user->setBusinessName('LivrExpress Staff SEED');
        $user->setBusinessPhone('0600000001');
        $user->setCity($city);

        $this->entityManager->persist($user);

        return $user;
    }

    /**
     * @return array<string, Colis>
     */
    private function seedColis(string $city): array
    {
        $scenarios = [
            'attente_pickup' => [
                'order' => 900001,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_CREE,
                'statut' => Colis::STATUT_EN_ATTENTE,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '350.00',
                'recipient' => 'Ahmed Test Pickup',
                'comment' => 'Colis en attente de ramassage',
            ],
            'en_cours_preparation' => [
                'order' => 900002,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EN_PREPARATION,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '420.00',
                'recipient' => 'Fatima En Cours',
            ],
            'expedie' => [
                'order' => 900003,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '280.00',
                'recipient' => 'Youssef Expédié',
            ],
            'livre_crbt' => [
                'order' => 900004,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_LIVRE,
                'statut' => Colis::STATUT_TERMINE,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '500.00',
                'deliveryFee' => '25.00',
                'recipient' => 'Sara Livrée CRBT',
            ],
            'livre_cod' => [
                'order' => 900005,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_LIVRE,
                'statut' => Colis::STATUT_TERMINE,
                'payment' => Colis::PAYMENT_COD,
                'price' => '750.00',
                'deliveryFee' => '30.00',
                'fragile' => true,
                'cartonOption' => 'm',
                'recipient' => 'Karim Livré COD',
            ],
            'en_attente_crbt' => [
                'order' => 900006,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '199.00',
                'recipient' => 'Nadia CRBT en attente',
            ],
            'retourne' => [
                'order' => 900007,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_RETOUR,
                'statut' => Colis::STATUT_TERMINE,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '310.00',
                'recipient' => 'Omar Retourné',
            ],
            'reporte' => [
                'order' => 900008,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_REPORTE,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '165.00',
                'recipient' => 'Laila Reportée',
            ],
            'echec' => [
                'order' => 900009,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_ECHEC,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '220.00',
                'recipient' => 'Hassan Échec',
            ],
            'stock_attente' => [
                'order' => 900010,
                'type' => Colis::TYPE_STOCK,
                'etat' => Colis::ETAT_CREE,
                'statut' => Colis::STATUT_EN_ATTENTE,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '890.00',
                'productNature' => 'T-shirt coton',
                'recipient' => 'Stock Attente',
            ],
            'stock_en_cours' => [
                'order' => 900011,
                'type' => Colis::TYPE_STOCK,
                'etat' => Colis::ETAT_EN_PREPARATION,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '640.00',
                'productNature' => 'Sneakers',
                'recipient' => 'Stock En cours',
            ],
            'suivi_change' => [
                'order' => 900012,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '455.00',
                'recipient' => 'Ancien Destinataire',
                'comment' => 'Changement destinataire',
            ],
            'bon_livraison_1' => [
                'order' => 900013,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EN_PREPARATION,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '380.00',
                'recipient' => 'BL Colis 1',
            ],
            'bon_livraison_2' => [
                'order' => 900014,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EN_PREPARATION,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '410.00',
                'recipient' => 'BL Colis 2',
            ],
            'retour_pending_1' => [
                'order' => 900015,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '275.00',
                'recipient' => 'Retour Pending 1',
            ],
            'retour_pending_2' => [
                'order' => 900016,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '295.00',
                'recipient' => 'Retour Pending 2',
            ],
            'retour_processing' => [
                'order' => 900017,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '330.00',
                'recipient' => 'Retour Processing',
            ],
            'retour_received' => [
                'order' => 900018,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_EXPEDIE,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '360.00',
                'recipient' => 'Retour Reçu',
            ],
            'bon_annule' => [
                'order' => 900019,
                'type' => Colis::TYPE_SIMPLE,
                'etat' => Colis::ETAT_CREE,
                'statut' => Colis::STATUT_EN_COURS,
                'payment' => Colis::PAYMENT_CRBT,
                'price' => '150.00',
                'recipient' => 'BL Annulé',
            ],
        ];

        $map = [];
        foreach ($scenarios as $key => $data) {
            $existing = $this->colisRepository->findOneBy(['orderNumber' => 'CMD-' . $data['order']]);
            if ($existing instanceof Colis) {
                $map[$key] = $existing;
                continue;
            }

            $colis = $this->buildColis($data, $city);
            $this->entityManager->persist($colis);
            $map[$key] = $colis;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildColis(array $data, string $city): Colis
    {
        $colis = new Colis();
        $colis->setOrderNumber((string) $data['order']);
        $colis->setType($data['type']);
        $colis->setCity($city);
        $colis->setAddress('45 Avenue Test');
        $colis->setNeighborhood('Quartier Seed');
        $colis->setPhoneNumber('0611223344');
        $colis->setPrice($data['price']);
        $colis->setProductNature($data['productNature'] ?? 'Produit test');
        $colis->setRecipient($data['recipient']);
        $colis->setPackageOption('Ne pas ouvrir le colis');
        $colis->setEtat($data['etat']);
        $colis->setStatut($data['statut']);
        $colis->setPaymentType($data['payment']);
        $colis->setComment($data['comment'] ?? 'Donnée de démonstration');

        if (isset($data['deliveryFee'])) {
            $colis->setDeliveryFee($data['deliveryFee']);
        }
        if (!empty($data['fragile'])) {
            $colis->setFragile(true);
        }
        if (!empty($data['cartonOption'])) {
            $colis->setCartonOption($data['cartonOption']);
        }

        return $colis;
    }

    private function seedCrbtPaye(Colis $colis): void
    {
        $crbt = $this->crbtRepository->findOneByColis($colis);
        if ($crbt === null) {
            return;
        }

        $crbt->setStatus(Crbt::STATUS_PAYE);
        $crbt->setPaidAt(new \DateTimeImmutable('-2 days'));
    }

    /**
     * @return list<PickupRequest>
     */
    private function seedPickupRequests(User $client, string $city): array
    {
        $scenarios = [
            ['status' => 'pending', 'type' => 'simple', 'note' => self::PICKUP_NOTE_PREFIX . 'en attente', 'driver' => null, 'days' => null],
            ['status' => 'confirmed', 'type' => 'simple', 'note' => self::PICKUP_NOTE_PREFIX . 'confirmé', 'driver' => 'Mohamed Livreur', 'days' => 1],
            ['status' => 'picked_up', 'type' => 'simple', 'note' => self::PICKUP_NOTE_PREFIX . 'effectué', 'driver' => 'Ali Livreur', 'days' => -1],
            ['status' => 'cancelled', 'type' => 'simple', 'note' => self::PICKUP_NOTE_PREFIX . 'annulé', 'driver' => null, 'days' => null],
        ];

        $pickups = [];
        foreach ($scenarios as $index => $scenario) {
            $note = $scenario['note'];
            $existing = $this->entityManager->getRepository(PickupRequest::class)->findOneBy(['note' => $note]);
            if ($existing instanceof PickupRequest) {
                $pickups[] = $existing;
                continue;
            }

            $pickup = new PickupRequest();
            $pickup->setProductNameSnapshot('Colis simple test');
            $pickup->setCity($city);
            $pickup->setNeighborhood('Maarif');
            $pickup->setAddress('10 Rue Ramassage Test ' . ($index + 1));
            $pickup->setPhone('06110000' . str_pad((string) $index, 2, '0', STR_PAD_LEFT));
            $pickup->setNote($note);
            $pickup->setStatus($scenario['status']);
            $pickup->setType($scenario['type']);
            $pickup->setCreatedBy($client);

            if ($scenario['driver'] !== null) {
                $pickup->setAssignedDriver($scenario['driver']);
            }
            if ($scenario['days'] !== null) {
                $pickup->setScheduledAt(new \DateTimeImmutable(($scenario['days'] >= 0 ? '+' : '') . $scenario['days'] . ' days 10:00'));
            }

            $this->entityManager->persist($pickup);
            $pickups[] = $pickup;
        }

        return $pickups;
    }

    /**
     * @param array<string, Colis> $colisMap
     *
     * @return list<BonLivraison>
     */
    private function seedBonLivraison(User $client, array $colisMap): array
    {
        $bons = [];

        $enregistre = null;
        foreach ($this->entityManager->getRepository(BonLivraison::class)->findBy(['status' => BonLivraison::STATUS_ENREGISTRE]) as $candidate) {
            if ($this->isTestBonLivraison($candidate)) {
                $enregistre = $candidate;
                break;
            }
        }

        if (!$enregistre instanceof BonLivraison) {
            $bon = new BonLivraison();
            $bon->setCreatedBy($client);
            $bon->generateReference();
            $bon->setStatus(BonLivraison::STATUS_ENREGISTRE);
            $bon->setRegisteredAt(new \DateTimeImmutable('-1 day'));
            $bon->addColis($colisMap['bon_livraison_1']);
            $bon->addColis($colisMap['bon_livraison_2']);
            $this->entityManager->persist($bon);
            $bons[] = $bon;
        }

        $annule = null;
        foreach ($this->entityManager->getRepository(BonLivraison::class)->findBy(['status' => BonLivraison::STATUS_ANNULE]) as $candidate) {
            if ($this->isTestBonLivraison($candidate)) {
                $annule = $candidate;
                break;
            }
        }

        if (!$annule instanceof BonLivraison) {
            $bon = new BonLivraison();
            $bon->setCreatedBy($client);
            $bon->generateReference();
            $bon->setStatus(BonLivraison::STATUS_ANNULE);
            $bon->addColis($colisMap['bon_annule']);
            $this->entityManager->persist($bon);
            $bons[] = $bon;
        }

        return $bons;
    }

    /**
     * @param array<string, Colis> $colisMap
     *
     * @return list<ReturnRequest>
     */
    private function seedReturnRequests(User $client, array $colisMap): array
    {
        $scenarios = [
            [
                'status' => ReturnRequest::STATUS_PENDING,
                'note' => self::RETURN_NOTE_PREFIX . 'en attente',
                'colis' => ['retour_pending_1', 'retour_pending_2'],
                'receivedAt' => null,
            ],
            [
                'status' => ReturnRequest::STATUS_PROCESSING,
                'note' => self::RETURN_NOTE_PREFIX . 'en traitement',
                'colis' => ['retour_processing'],
                'receivedAt' => null,
            ],
            [
                'status' => ReturnRequest::STATUS_RECEIVED,
                'note' => self::RETURN_NOTE_PREFIX . 'reçue',
                'colis' => ['retour_received'],
                'receivedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'status' => ReturnRequest::STATUS_CANCELLED,
                'note' => self::RETURN_NOTE_PREFIX . 'annulée',
                'colis' => ['en_cours_preparation'],
                'receivedAt' => null,
            ],
        ];

        $requests = [];
        foreach ($scenarios as $scenario) {
            $existing = $this->entityManager->getRepository(ReturnRequest::class)->findOneBy(['note' => $scenario['note']]);
            if ($existing instanceof ReturnRequest) {
                $requests[] = $existing;
                continue;
            }

            $request = new ReturnRequest();
            $request->setCreatedBy($client);
            $request->setReceptionType('En Agence');
            $request->setNote($scenario['note']);
            $request->setStatus($scenario['status']);
            $request->generateBonReference();

            if ($scenario['receivedAt'] instanceof \DateTimeImmutable) {
                $request->setReceivedAt($scenario['receivedAt']);
            }

            foreach ($scenario['colis'] as $key) {
                $request->addColis($colisMap[$key]);
            }

            $this->entityManager->persist($request);
            $requests[] = $request;
        }

        return $requests;
    }

    /**
     * @return array{products: int, movements: int}
     */
    private function seedStock(): array
    {
        $productWithVariants = $this->entityManager->getRepository(StockProduct::class)->findOneBy([
            'barcode' => self::STOCK_BARCODE_PREFIX . 'TSHIRT-001',
        ]);

        if (!$productWithVariants instanceof StockProduct) {
            $productWithVariants = new StockProduct('T-shirt Premium', '3');
            $productWithVariants->setBarcode(self::STOCK_BARCODE_PREFIX . 'TSHIRT-001');
            $productWithVariants->setQuantity(120);
            $productWithVariants->setNote('Produit avec variantes S/M/L');
            $productWithVariants->addVariant(new StockProductVariant('S', 40));
            $productWithVariants->addVariant(new StockProductVariant('M', 50));
            $productWithVariants->addVariant(new StockProductVariant('L', 30));
            $this->entityManager->persist($productWithVariants);
        }

        $productSimple = $this->entityManager->getRepository(StockProduct::class)->findOneBy([
            'barcode' => self::STOCK_BARCODE_PREFIX . 'CAP-001',
        ]);

        if (!$productSimple instanceof StockProduct) {
            $productSimple = new StockProduct('Casquette Logo', '5');
            $productSimple->setBarcode(self::STOCK_BARCODE_PREFIX . 'CAP-001');
            $productSimple->setQuantity(75);
            $productSimple->addVariant(new StockProductVariant('Unique', 75));
            $this->entityManager->persist($productSimple);
        }

        $variants = $productWithVariants->getVariants()->toArray();
        $variantS = $variants[0] ?? null;

        $movementStatuses = [
            StockMovement::STATUS_DRAFT,
            StockMovement::STATUS_PENDING,
            StockMovement::STATUS_IN_PROGRESS,
            StockMovement::STATUS_DONE,
            StockMovement::STATUS_CANCELLED,
        ];

        $movementCount = 0;
        foreach ($movementStatuses as $index => $status) {
            $reference = self::MOVEMENT_PREFIX . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $existing = $this->entityManager->getRepository(StockMovement::class)->findOneBy(['reference' => $reference]);
            if ($existing instanceof StockMovement) {
                ++$movementCount;
                continue;
            }

            $movement = new StockMovement($reference);
            $movement->setDirection($index % 2 === 0 ? StockMovement::DIRECTION_ENTRY : StockMovement::DIRECTION_EXIT);
            $movement->setStatus($status);

            if ($variantS instanceof StockProductVariant) {
                $movement->addItem(new StockMovementItem($variantS, 5 + $index));
            }

            $this->entityManager->persist($movement);
            ++$movementCount;
        }

        $stockPickupNote = self::PICKUP_NOTE_PREFIX . 'stock produit';
        $stockPickup = $this->entityManager->getRepository(PickupRequest::class)->findOneBy(['note' => $stockPickupNote]);
        if (!$stockPickup instanceof PickupRequest && $productWithVariants->getId() !== null) {
            $pickup = new PickupRequest();
            $pickup->setProduct($productWithVariants);
            $pickup->setProductNameSnapshot($productWithVariants->getName());
            $pickup->setCity('Casablanca');
            $pickup->setNeighborhood('Ain Sebaa');
            $pickup->setAddress('Zone industrielle test');
            $pickup->setPhone('0622334455');
            $pickup->setNote($stockPickupNote);
            $pickup->setStatus('confirmed');
            $pickup->setType('stock');
            $pickup->setScheduledAt(new \DateTimeImmutable('+2 days 14:00'));
            $this->entityManager->persist($pickup);
        }

        return [
            'products' => 2,
            'movements' => $movementCount,
        ];
    }

    /**
     * @return list<WhatsAppTemplate>
     */
    private function seedWhatsAppTemplates(): array
    {
        $templates = [];
        $definitions = [
            [
                'title' => self::WHATSAPP_TITLE_PREFIX . 'Livraison active',
                'message' => 'Bonjour @name, votre colis @product est en route vers @address. Livreur: @numLivreur',
                'status' => WhatsAppTemplate::STATUS_ACTIVE,
            ],
            [
                'title' => self::WHATSAPP_TITLE_PREFIX . 'Rappel inactif',
                'message' => 'Rappel: contactez le client @name au @numClient pour le colis @product.',
                'status' => WhatsAppTemplate::STATUS_INACTIVE,
            ],
        ];

        foreach ($definitions as $definition) {
            $existing = $this->entityManager->getRepository(WhatsAppTemplate::class)->findOneBy([
                'title' => $definition['title'],
            ]);
            if ($existing instanceof WhatsAppTemplate) {
                $templates[] = $existing;
                continue;
            }

            $template = new WhatsAppTemplate();
            $template->setTitle($definition['title']);
            $template->setMessage($definition['message']);
            $template->setStatus($definition['status']);
            $this->entityManager->persist($template);
            $templates[] = $template;
        }

        return $templates;
    }

  /**
     * @return list<Colis>
     */
    private function findTestColis(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Colis::class, 'c')
            ->where('c.orderNumber LIKE :prefix')
            ->setParameter('prefix', 'CMD-' . self::COLIS_ORDER_PREFIX . '%')
            ->getQuery()
            ->getResult();
    }

    private function isTestBonLivraison(?BonLivraison $bon): bool
    {
        if (!$bon instanceof BonLivraison) {
            return false;
        }

        foreach ($bon->getColis() as $colis) {
            $order = $colis->getOrderNumber() ?? '';
            if (str_starts_with($order, 'CMD-' . self::COLIS_ORDER_PREFIX)) {
                return true;
            }
        }

        return false;
    }

    private function isTestReturnRequest(ReturnRequest $request, ?User $client): bool
    {
        if ($client !== null && $request->getCreatedBy()?->getId() === $client->getId()) {
            return true;
        }

        $note = $request->getNote() ?? '';

        return str_starts_with($note, self::RETURN_NOTE_PREFIX)
            || str_starts_with($note, '[TEST]');
    }

    private function isTestPickup(PickupRequest $pickup, ?User $client): bool
    {
        if ($client !== null && $pickup->getCreatedBy()?->getId() === $client->getId()) {
            return true;
        }

        $note = $pickup->getNote() ?? '';

        return str_starts_with($note, self::PICKUP_NOTE_PREFIX)
            || str_starts_with($note, '[TEST]');
    }

    public function stripTestMarkersFromDatabase(): int
    {
        $connection = $this->entityManager->getConnection();
        $updated = 0;

        $tables = [
            ['colis', 'comment'],
            ['pickup_request', 'note'],
            ['return_request', 'note'],
            ['stock_product', 'name'],
            ['stock_product', 'note'],
            ['whatsapp_template', 'title'],
        ];

        foreach ($tables as [$table, $column]) {
            $updated += (int) $connection->executeStatement(
                sprintf(
                    "UPDATE `%s` SET `%s` = TRIM(REPLACE(REPLACE(`%s`, '[TEST] ', ''), '[TEST]', '')) WHERE `%s` LIKE '%%[TEST]%%'",
                    $table,
                    $column,
                    $column,
                    $column
                )
            );
        }

        return $updated;
    }
}
