<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\UserSettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/settings')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        private readonly UserSettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_settings_get', methods: ['GET'])]
    public function getSettings(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $settings = $this->settingsManager->getOrCreateForUser($user);

        return $this->json([
            'parcel_settings' => $settings->getParcelSettings(),
            'packaging_settings' => $settings->getPackagingSettings(),
        ]);
    }

    #[Route('', name: 'api_settings_put', methods: ['PUT'])]
    public function putSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $settings = $this->settingsManager->getOrCreateForUser($user);

        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!\is_array($payload)) {
            throw new BadRequestHttpException('Invalid JSON body.');
        }

        if (array_key_exists('parcel_settings', $payload)) {
            $parcel = $payload['parcel_settings'];
            if (!\is_array($parcel)) {
                throw new BadRequestHttpException('parcel_settings must be an object.');
            }
            $settings->setParcelSettings($this->validateAndNormalizeParcelSettings($parcel));

            // Keep backward compatibility with existing behavior based on User::packageOption.
            if (($parcel['open_colis']['enabled'] ?? false) === true) {
                $user->setPackageOption('Ouvrir le colis');
            } else {
                $user->setPackageOption('Ne pas ouvrir le colis');
            }
        }

        if (array_key_exists('packaging_settings', $payload)) {
            $packaging = $payload['packaging_settings'];
            if (!\is_array($packaging)) {
                throw new BadRequestHttpException('packaging_settings must be an object.');
            }
            $settings->setPackagingSettings($this->validateAndNormalizePackagingSettings($packaging));
        }

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'parcel_settings' => $settings->getParcelSettings(),
            'packaging_settings' => $settings->getPackagingSettings(),
        ]);
    }

    private function validateAndNormalizeParcelSettings(array $input): array
    {
        $defaults = $this->settingsManager->defaultParcelSettings();
        $out = $defaults;

        foreach (['fragile', 'open_colis', 'unique_order_number'] as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            if (!\is_array($input[$key])) {
                throw new BadRequestHttpException(sprintf('%s must be an object.', $key));
            }
            $enabled = (bool) ($input[$key]['enabled'] ?? $defaults[$key]['enabled']);
            $fee = $input[$key]['fee'] ?? $defaults[$key]['fee'];

            if (!\is_numeric($fee) || (float) $fee < 0) {
                throw new BadRequestHttpException(sprintf('%s.fee must be a number >= 0.', $key));
            }

            $out[$key] = [
                'enabled' => $enabled,
                'fee' => (float) $fee,
            ];
        }

        return $out;
    }

    private function validateAndNormalizePackagingSettings(array $input): array
    {
        $defaults = $this->settingsManager->defaultPackagingSettings();
        $out = $defaults;

        $allowedSelected = ['none', 'S', 'M', 'L'];
        foreach (['cartons', 'sachets', 'bubble_wrap'] as $category) {
            if (!array_key_exists($category, $input)) {
                continue;
            }
            if (!\is_array($input[$category])) {
                throw new BadRequestHttpException(sprintf('%s must be an object.', $category));
            }

            $selected = (string) ($input[$category]['selected'] ?? $defaults[$category]['selected']);
            if (!\in_array($selected, $allowedSelected, true)) {
                throw new BadRequestHttpException(sprintf('%s.selected must be one of none,S,M,L.', $category));
            }

            $enabled = (bool) ($input[$category]['enabled'] ?? $defaults[$category]['enabled']);
            if ($selected === 'none') {
                $enabled = false;
            } else {
                $enabled = true;
            }

            $fees = $input[$category]['fees'] ?? $defaults[$category]['fees'];
            if (!\is_array($fees)) {
                throw new BadRequestHttpException(sprintf('%s.fees must be an object.', $category));
            }
            foreach (['S', 'M', 'L'] as $size) {
                $val = $fees[$size] ?? $defaults[$category]['fees'][$size];
                if (!\is_numeric($val) || (float) $val < 0) {
                    throw new BadRequestHttpException(sprintf('%s.fees.%s must be a number >= 0.', $category, $size));
                }
                $fees[$size] = (float) $val;
            }

            $out[$category] = [
                'enabled' => $enabled,
                'selected' => $selected,
                'fees' => [
                    'S' => (float) $fees['S'],
                    'M' => (float) $fees['M'],
                    'L' => (float) $fees['L'],
                ],
            ];
        }

        return $out;
    }
}

