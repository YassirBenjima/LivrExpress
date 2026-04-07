<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(CityRepository $cityRepository): Response
    {
        $user = $this->getUser();
        $profileAvatarFilename = $user instanceof User ? $user->getAvatar() : null;
        $cities = $cityRepository->findBy([], ['name' => 'ASC']);
        $moroccanBanks = [
            'Attijariwafa bank',
            'Banque Populaire',
            'Bank of Africa',
            'BMCI',
            'CIH Bank',
            'Crédit Agricole du Maroc',
            'Crédit du Maroc',
            'Société Générale Maroc',
            'Al Barid Bank',
            'Bank Assafa',
            'Umnia Bank',
            'Bank Al Yousr',
            'BTI Bank',
            'CFG Bank',
            'Arab Bank Maroc',
            'Sabadell',
        ];

        return $this->render('profile/index.html.twig', [
            'profileAvatarFilename' => $profileAvatarFilename,
            'cities' => $cities,
            'moroccanBanks' => $moroccanBanks,
        ]);
    }

    #[Route('/profile/avatar', name: 'app_profile_avatar_update', methods: ['POST'])]
    public function updateAvatar(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        if (!$this->isCsrfTokenValid('profile_avatar', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not authenticated.');
        }

        $avatarFile = $request->files->get('avatar');
        $removeAvatar = (string) $request->request->get('avatar_remove') === '1';
        $avatarsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
        $currentAvatar = $user->getAvatar();

        if (!is_dir($avatarsDir)) {
            mkdir($avatarsDir, 0775, true);
        }

        if ($removeAvatar && $currentAvatar) {
            $oldPath = $avatarsDir . '/' . $currentAvatar;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
            $user->setAvatar(null);
        }

        if ($avatarFile instanceof UploadedFile && $avatarFile->isValid()) {
            if ($currentAvatar) {
                $oldPath = $avatarsDir . '/' . $currentAvatar;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $originalName = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName = $slugger->slug($originalName ?: 'avatar')->lower();
            $extension = $avatarFile->guessExtension() ?: 'bin';
            $newFilename = sprintf('%s-%s.%s', $safeName, uniqid(), $extension);
            $avatarFile->move($avatarsDir, $newFilename);

            $user->setAvatar($newFilename);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/name', name: 'app_profile_name_update', methods: ['POST'])]
    public function updateName(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('profile_name', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not authenticated.');
        }

        $fullName = trim((string) $request->request->get('full_name'));
        if ($fullName !== '') {
            $user->setFullName($fullName);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/field', name: 'app_profile_field_update', methods: ['POST'])]
    public function updateField(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('profile_field', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not authenticated.');
        }

        $field = (string) $request->request->get('field');
        $value = trim((string) $request->request->get('value'));

        switch ($field) {
            case 'full_name':
                if ($value !== '') {
                    $user->setFullName($value);
                }
                break;
            case 'business_name':
                if ($value !== '') {
                    $user->setBusinessName($value);
                }
                break;
            case 'personal_phone':
            case 'business_phone':
                if ($value !== '') {
                    $user->setBusinessPhone($value);
                }
                break;
            case 'city':
                if ($value !== '') {
                    $user->setCity($value);
                }
                break;
            case 'address':
                $user->setAddress($value !== '' ? $value : null);
                break;
            case 'client_type':
                $user->setClientType($value !== '' ? $value : null);
                break;
            case 'ice':
                $user->setIce($value !== '' ? $value : null);
                break;
            case 'website':
                $user->setWebsite($value !== '' ? $value : null);
                break;
            case 'rc':
                $user->setRc($value !== '' ? $value : null);
                break;
            case 'label_message':
                $user->setLabelMessage($value !== '' ? $value : null);
                break;
            case 'package_option':
                $user->setPackageOption($value !== '' ? $value : null);
                break;
            case 'bank_name':
                $user->setBankName($value !== '' ? $value : null);
                break;
            case 'bank_rib':
                $rib = preg_replace('/\D+/', '', $value) ?? '';
                if ($rib !== '' && strlen($rib) !== 24) {
                    $this->addFlash('error', 'Le RIB doit contenir exactement 24 chiffres.');

                    return $this->redirectToRoute('app_profile');
                }
                $user->setBankRib($rib !== '' ? $rib : null);
                break;
            case 'email':
                if ($value !== '' && $value !== $user->getEmail()) {
                    $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $value]);
                    if ($existingUser instanceof User && $existingUser->getId() !== $user->getId()) {
                        $this->addFlash('error', 'Cet email est deja utilise.');

                        return $this->redirectToRoute('app_profile');
                    }

                    $user->setEmail($value);
                }
                break;
            default:
                throw $this->createNotFoundException('Field not supported.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_profile');
    }
}
