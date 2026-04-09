<?php

namespace App\Controller;

use App\Entity\Colis;
use App\Entity\User;
use App\Form\ColisType;
use App\Repository\CityRepository;
use App\Repository\ColisRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/colis')]
final class ColisController extends AbstractController
{
    #[Route('', name: 'app_colis_index', methods: ['GET'])]
    public function index(Request $request, ColisRepository $colisRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $selectedEtat = self::normalizeEtat(trim((string) $request->query->get('etat', '')));
        $selectedStatut = self::normalizeStatut(trim((string) $request->query->get('statut', '')));

        $colisList = $colisRepository->findBy([], ['id' => 'DESC']);
        $colisList = array_values(array_filter($colisList, static function (Colis $colis) use ($search, $selectedEtat, $selectedStatut): bool {
            $etat = self::normalizeEtat((string) ($colis->getEtat() ?? Colis::ETAT_CREE));
            $statut = self::normalizeStatut((string) ($colis->getStatut() ?? Colis::STATUT_EN_ATTENTE));

            if ($selectedEtat !== '' && $etat !== $selectedEtat) {
                return false;
            }

            if ($selectedStatut !== '' && $statut !== $selectedStatut) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', [
                (string) $colis->getTrackingCode(),
                (string) $colis->getOrderNumber(),
                (string) $colis->getProductNature(),
                (string) $colis->getCity(),
                (string) $colis->getAddress(),
                (string) $colis->getRecipient(),
                $etat,
                $statut,
            ]));

            return str_contains($haystack, mb_strtolower($search));
        }));

        return $this->render('colis/index.html.twig', [
            'colis_list' => $colisList,
            'etats_possibles' => Colis::getEtatsPossibles(),
            'statuts_possibles' => Colis::getStatutsPossibles(),
            'search_query' => $search,
            'selected_etat' => $selectedEtat,
            'selected_statut' => $selectedStatut,
        ]);
    }

    private static function normalizeEtat(string $etat): string
    {
        return match ($etat) {
            'Cree' => Colis::ETAT_CREE,
            'En preparation' => Colis::ETAT_EN_PREPARATION,
            'Expedie' => Colis::ETAT_EXPEDIE,
            'Livre' => Colis::ETAT_LIVRE,
            'Retour' => Colis::ETAT_RETOUR,
            default => $etat,
        };
    }

    private static function normalizeStatut(string $statut): string
    {
        return match ($statut) {
            'Reporte' => Colis::STATUT_REPORTE,
            'Echec' => Colis::STATUT_ECHEC,
            'Termine' => Colis::STATUT_TERMINE,
            default => $statut,
        };
    }

    #[Route('/new', name: 'app_colis_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CityRepository $cityRepository, ColisRepository $colisRepository): Response
    {
        $colis = new Colis();
        $cityChoices = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $city) {
            $cityName = (string) $city->getName();
            $cityChoices[$cityName] = $cityName;
        }
        $oldColisChoices = [];
        foreach ($colisRepository->findBy([], ['id' => 'DESC']) as $oldColis) {
            $orderNumber = (string) $oldColis->getOrderNumber();
            $oldColisChoices[$orderNumber] = $orderNumber;
        }
        $user = $this->getUser();
        $defaultPackageOption = $user instanceof User ? $user->getPackageOption() : null;
        if (!$defaultPackageOption) {
            $defaultPackageOption = 'Ne pas ouvrir le colis';
        }

        $form = $this->createForm(ColisType::class, $colis, [
            'city_choices' => $cityChoices,
            'old_colis_choices' => $oldColisChoices,
            'default_package_option' => $defaultPackageOption,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedData = $request->request->all((string) $form->getName());
            $submittedCartonOption = $submittedData['cartonOption'] ?? null;
            $submittedOldColis = $submittedData['oldColis'] ?? null;
            $allowedCartonOptions = ['none', 's', 'm', 'l'];
            if (!\is_string($submittedCartonOption) || !\in_array($submittedCartonOption, $allowedCartonOptions, true)) {
                $submittedCartonOption = null;
            }
            $colis->setCartonOption($submittedCartonOption);

            if (\is_string($submittedOldColis) && $submittedOldColis !== '') {
                $colis->setOldOrderNumber($submittedOldColis);
            }

            if (!$colis->isReplacePackage()) {
                $colis->setOldOrderNumber(null);
            }

            if (!$colis->getCartonOption()) {
                $colis->setCartonOption(null);
            }

            try {
                $entityManager->persist($colis);
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Numero de commande deja utilise. Veuillez saisir un numero unique.');

                return $this->redirectToRoute('app_colis_new');
            }

            $this->addFlash('success', 'Colis ajoute avec succes.');

            return $this->redirectToRoute('app_colis_index');
        }

        return $this->render('colis/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/pickup', name: 'app_colis_pickup', methods: ['GET'])]
    public function pickup(ColisRepository $colisRepository): Response
    {
        return $this->render('colis/pickup.html.twig', [
            'colis_list' => $colisRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/import', name: 'app_colis_import', methods: ['GET'])]
    public function import(): Response
    {
        return $this->render('colis/import.html.twig');
    }

    #[Route('/settings', name: 'app_colis_settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('colis/settings.html.twig');
    }
}
