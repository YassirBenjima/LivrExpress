<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        CityRepository $cityRepository
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');
            $fullName = $request->request->get('full_name');
            $businessName = $request->request->get('business_name');
            $businessPhone = $request->request->get('business_phone');
            $city = $request->request->get('city');

            if ($password !== $confirmPassword) {
                $error = 'Les mots de passe ne correspondent pas.';
            } else {
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existingUser) {
                    $error = 'Cet email est déjà utilisé.';
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFullName($fullName);
                    $user->setBusinessName($businessName);
                    $user->setBusinessPhone($businessPhone);
                    $user->setCity($city);
                    
                    // Hash the password
                    $user->setPassword(
                        $userPasswordHasher->hashPassword(
                            $user,
                            $password
                        )
                    );

                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre compte a été créé avec succès ! Connectez-vous maintenant.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        $cities = $cityRepository->findBy([], ['name' => 'ASC']);

        return $this->render('registration/register.html.twig', [
            'cities' => $cities,
            'error' => $error,
        ]);
    }
}
