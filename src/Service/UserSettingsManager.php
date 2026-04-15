<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserSettings;
use App\Repository\UserSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserSettingsManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserSettingsRepository $repository,
    ) {
    }

    public function getOrCreateForUser(User $user): UserSettings
    {
        $existing = $this->repository->findOneByUser($user);
        if ($existing) {
            return $existing;
        }

        $settings = (new UserSettings())
            ->setUser($user)
            ->setParcelSettings($this->defaultParcelSettings())
            ->setPackagingSettings($this->defaultPackagingSettings());

        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        return $settings;
    }

    public function defaultParcelSettings(): array
    {
        return [
            // Keep this minimal; existing UI currently shows these options.
            'fragile' => [
                'enabled' => false,
                'fee' => 5.0,
            ],
            'open_colis' => [
                'enabled' => false,
                'fee' => 0.0,
            ],
            'unique_order_number' => [
                'enabled' => false,
                'fee' => 0.0,
            ],
        ];
    }

    public function defaultPackagingSettings(): array
    {
        return [
            'cartons' => [
                'enabled' => false,
                'selected' => 'none',
                'fees' => [
                    'S' => 1.5,
                    'M' => 2.5,
                    'L' => 3.0,
                ],
            ],
            'sachets' => [
                'enabled' => false,
                'selected' => 'none',
                'fees' => [
                    'S' => 1.0,
                    'M' => 1.5,
                    'L' => 2.0,
                ],
            ],
            'bubble_wrap' => [
                'enabled' => false,
                'selected' => 'none',
                'fees' => [
                    'S' => 1.5,
                    'M' => 2.0,
                    'L' => 2.5,
                ],
            ],
        ];
    }
}

