<?php

namespace App\Controller;

use App\Entity\Colis;
use App\Repository\CityRepository;
use App\Repository\ColisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/suivi')]
final class TrackingController extends AbstractController
{
    #[Route('/changement-destinataire', name: 'app_suivi_changement_destinataire', methods: ['GET'])]
    public function changementDestinataire(
        Request $request,
        ColisRepository $colisRepository,
        CityRepository $cityRepository,
    ): Response {
        $tab = $this->normalizeTab((string) $request->query->get('tab', 'same'));
        $search = trim((string) $request->query->get('q', ''));
        $selectedCity = trim((string) $request->query->get('city', ''));

        $dateFrom = $this->parseDateOnly((string) $request->query->get('date_from', ''));
        $dateTo = $this->parseDateOnly((string) $request->query->get('date_to', ''));

        $colisList = $this->filterColis(
            $colisRepository->findBy([], ['id' => 'DESC']),
            $tab,
            $search,
            $selectedCity,
            $dateFrom,
            $dateTo,
        );

        $cityChoices = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $city) {
            $name = (string) $city->getName();
            $cityChoices[$name] = $name;
        }

        return $this->render('tracking/change_recipient.html.twig', [
            'tab' => $tab,
            'colis_list' => $colisList,
            'search_query' => $search,
            'selected_city' => $selectedCity,
            'date_from' => $dateFrom?->format('Y-m-d'),
            'date_to' => $dateTo?->format('Y-m-d'),
            'city_choices' => array_values($cityChoices),
        ]);
    }

    #[Route('/changement-destinataire/export.csv', name: 'app_suivi_changement_destinataire_export', methods: ['GET'])]
    public function exportChangementDestinataireCsv(
        Request $request,
        ColisRepository $colisRepository,
    ): Response {
        $tab = $this->normalizeTab((string) $request->query->get('tab', 'same'));
        $search = trim((string) $request->query->get('q', ''));
        $selectedCity = trim((string) $request->query->get('city', ''));

        $dateFrom = $this->parseDateOnly((string) $request->query->get('date_from', ''));
        $dateTo = $this->parseDateOnly((string) $request->query->get('date_to', ''));

        $colisList = $this->filterColis(
            $colisRepository->findBy([], ['id' => 'DESC']),
            $tab,
            $search,
            $selectedCity,
            $dateFrom,
            $dateTo,
        );

        $response = new StreamedResponse(function () use ($colisList): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'ID',
                'Code suivi',
                'Nom du produit',
                'Date creation',
                'Destinataire',
                'Telephone',
                'Ville',
                'Adresse',
                'Prix',
                'Statut',
                'Etat',
                'Commentaire',
            ], ';');

            foreach ($colisList as $colis) {
                fputcsv($out, [
                    (string) $colis->getId(),
                    (string) $colis->getTrackingCode(),
                    (string) $colis->getProductNature(),
                    $colis->getCreatedAt() ? $colis->getCreatedAt()->format('Y-m-d H:i') : '',
                    (string) $colis->getRecipient(),
                    (string) $colis->getPhoneNumber(),
                    (string) $colis->getCity(),
                    (string) $colis->getAddress(),
                    (string) $colis->getPrice(),
                    (string) $colis->getStatut(),
                    (string) $colis->getEtat(),
                    (string) $colis->getComment(),
                ], ';');
            }

            fclose($out);
        });

        $filename = sprintf('changement-destinataire-%s.csv', (new \DateTimeImmutable())->format('Ymd-His'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    #[Route('/changement-destinataire/bulk/recipient', name: 'app_suivi_changement_destinataire_bulk_recipient', methods: ['POST'])]
    public function bulkChangeRecipient(
        Request $request,
        ColisRepository $colisRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('suivi_change_recipient_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_suivi_changement_destinataire');
        }

        $ids = $request->request->all('colis_ids');
        if (!\is_array($ids) || $ids === []) {
            $this->addFlash('error', 'Aucun colis selectionne.');

            return $this->redirectToRoute('app_suivi_changement_destinataire');
        }

        $recipient = trim((string) $request->request->get('recipient', ''));
        $phoneNumber = trim((string) $request->request->get('phoneNumber', ''));
        $address = trim((string) $request->request->get('address', ''));
        $neighborhood = trim((string) $request->request->get('neighborhood', ''));

        if ($recipient === '' && $phoneNumber === '' && $address === '' && $neighborhood === '') {
            $this->addFlash('error', 'Veuillez renseigner au moins un champ du destinataire.');

            return $this->redirectToRoute('app_suivi_changement_destinataire');
        }

        $updated = 0;
        foreach ($ids as $id) {
            if (!\is_scalar($id)) {
                continue;
            }

            $colis = $colisRepository->find((int) $id);
            if (!$colis) {
                continue;
            }

            if ($recipient !== '') {
                $colis->setRecipient($recipient);
            }
            if ($phoneNumber !== '') {
                $colis->setPhoneNumber($phoneNumber);
            }
            if ($address !== '') {
                $colis->setAddress($address);
            }
            if ($neighborhood !== '') {
                $colis->setNeighborhood($neighborhood);
            }

            ++$updated;
        }

        if ($updated > 0) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('%d colis mis a jour.', $updated));
        } else {
            $this->addFlash('error', 'Aucun colis valide a traiter.');
        }

        return $this->redirectToRoute('app_suivi_changement_destinataire', $this->getRedirectQuery($request));
    }

    #[Route('/changement-destinataire/bulk/city', name: 'app_suivi_changement_destinataire_bulk_city', methods: ['POST'])]
    public function bulkChangeCity(
        Request $request,
        ColisRepository $colisRepository,
        CityRepository $cityRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('suivi_change_city_bulk', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_suivi_changement_destinataire');
        }

        $ids = $request->request->all('colis_ids');
        if (!\is_array($ids) || $ids === []) {
            $this->addFlash('error', 'Aucun colis selectionne.');

            return $this->redirectToRoute('app_suivi_changement_destinataire');
        }

        $city = trim((string) $request->request->get('city', ''));
        if ($city === '') {
            $this->addFlash('error', 'Veuillez choisir une ville.');

            return $this->redirectToRoute('app_suivi_changement_destinataire', $this->getRedirectQuery($request));
        }

        $knownCities = [];
        foreach ($cityRepository->findBy([], ['name' => 'ASC']) as $knownCity) {
            $knownCities[(string) $knownCity->getName()] = true;
        }
        if (!isset($knownCities[$city])) {
            $this->addFlash('error', 'Ville invalide.');

            return $this->redirectToRoute('app_suivi_changement_destinataire', $this->getRedirectQuery($request));
        }

        $updated = 0;
        foreach ($ids as $id) {
            if (!\is_scalar($id)) {
                continue;
            }

            $colis = $colisRepository->find((int) $id);
            if (!$colis) {
                continue;
            }

            $colis->setCity($city);
            ++$updated;
        }

        if ($updated > 0) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('%d colis mis a jour.', $updated));
        } else {
            $this->addFlash('error', 'Aucun colis valide a traiter.');
        }

        return $this->redirectToRoute('app_suivi_changement_destinataire', $this->getRedirectQuery($request));
    }

    #[Route('/modele-whatsapp', name: 'app_suivi_modele_whatsapp', methods: ['GET'])]
    public function modeleWhatsapp(): Response
    {
        return $this->render('tracking/whatsapp_template.html.twig');
    }

    /**
     * @param list<Colis> $colisList
     * @return list<Colis>
     */
    private function filterColis(
        array $colisList,
        string $tab,
        string $search,
        string $selectedCity,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
    ): array {
        $searchLower = mb_strtolower($search);
        $selectedCityLower = mb_strtolower($selectedCity);

        return array_values(array_filter($colisList, static function (Colis $colis) use (
            $tab,
            $searchLower,
            $selectedCityLower,
            $dateFrom,
            $dateTo,
        ): bool {
            if ($selectedCityLower !== '' && mb_strtolower((string) $colis->getCity()) !== $selectedCityLower) {
                return false;
            }

            if ($dateFrom || $dateTo) {
                $created = $colis->getCreatedAt();
                if (!$created) {
                    return false;
                }

                $createdDay = \DateTimeImmutable::createFromFormat('Y-m-d', $created->format('Y-m-d'));
                if (!$createdDay) {
                    return false;
                }

                if ($dateFrom && $createdDay < $dateFrom) {
                    return false;
                }
                if ($dateTo && $createdDay > $dateTo) {
                    return false;
                }
            }

            if ($searchLower !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) $colis->getTrackingCode(),
                    (string) $colis->getOrderNumber(),
                    (string) $colis->getProductNature(),
                    (string) $colis->getCity(),
                    (string) $colis->getAddress(),
                    (string) $colis->getRecipient(),
                    (string) $colis->getPhoneNumber(),
                    (string) $colis->getComment(),
                    (string) $colis->getStatut(),
                    (string) $colis->getEtat(),
                ]));

                if (!str_contains($haystack, $searchLower)) {
                    return false;
                }
            }

            // Tabs are UI-driven: both tabs use same dataset, but enable/disable actions.
            return \in_array($tab, ['same', 'newcity'], true);
        }));
    }

    private function normalizeTab(string $tab): string
    {
        return match ($tab) {
            'newcity' => 'newcity',
            default => 'same',
        };
    }

    private function parseDateOnly(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date) {
            return null;
        }

        return $date;
    }

    /**
     * Preserve current filter query params after POST.
     *
     * @return array<string, string>
     */
    private function getRedirectQuery(Request $request): array
    {
        $query = [];
        $tab = (string) $request->request->get('tab', '');
        if ($tab !== '') {
            $query['tab'] = $tab;
        }

        $q = (string) $request->request->get('q', '');
        if ($q !== '') {
            $query['q'] = $q;
        }

        $filterCity = (string) $request->request->get('filter_city', '');
        if ($filterCity !== '') {
            $query['city'] = $filterCity;
        }

        foreach (['date_from', 'date_to'] as $key) {
            $value = (string) $request->request->get($key, '');
            if ($value !== '') {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
