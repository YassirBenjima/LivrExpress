<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        CityRepository $cityRepository,
        NotifierInterface $notifier
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
                $notifier->send((new Notification('Les mots de passe saisis ne correspondent pas.', ['browser']))->importance(Notification::IMPORTANCE_HIGH));
                return $this->redirectToRoute('app_register');
            } else {
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                $existingBusiness = $entityManager->getRepository(User::class)->findOneBy(['businessName' => $businessName]);
                
                if ($existingUser) {
                    $notifier->send((new Notification('Cette adresse email est déjà associée à un compte.', ['browser']))->importance(Notification::IMPORTANCE_HIGH));
                    return $this->redirectToRoute('app_register');
                } elseif ($existingBusiness) {
                    $notifier->send((new Notification('Ce nom d\'entreprise est déjà enregistré sur notre plateforme.', ['browser']))->importance(Notification::IMPORTANCE_HIGH));
                    return $this->redirectToRoute('app_register');
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFullName($fullName);
                    $user->setBusinessName($businessName);
                    $user->setBusinessPhone($businessPhone);
                    $user->setCity($city);
                    $user->setRoles(['ROLE_CLIENT']);
                    
                    // Hash the password
                    $user->setPassword(
                        $userPasswordHasher->hashPassword(
                            $user,
                            $password
                        )
                    );

                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.');
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
