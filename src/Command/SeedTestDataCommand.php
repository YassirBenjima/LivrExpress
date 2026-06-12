<?php

namespace App\Command;

use App\Repository\CityRepository;
use App\Service\TestDataSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-test-data',
    description: 'Génère des données de test pour tous les modules (colis, ramassage, BL, retour, CRBT, stock, suivi).',
)]
final class SeedTestDataCommand extends Command
{
    public function __construct(
        private readonly TestDataSeeder $seeder,
        private readonly CityRepository $cityRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Supprime les données de test existantes')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Réinitialise puis regénère les données de test')
            ->addOption('cleanup', null, InputOption::VALUE_NONE, 'Retire [TEST] de tous les champs en base sans regénérer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $purge = (bool) $input->getOption('purge');
        $force = (bool) $input->getOption('force');
        $cleanup = (bool) $input->getOption('cleanup');

        if ($cleanup) {
            $cleaned = $this->seeder->stripTestMarkersFromDatabase();
            $io->success(sprintf('%d champ(s) nettoyé(s) ([TEST] retiré).', $cleaned));

            return Command::SUCCESS;
        }

        if ($purge && !$force) {
            $cleaned = $this->seeder->stripTestMarkersFromDatabase();
            $this->seeder->purge();
            $io->success(sprintf('Données de test supprimées. %d champ(s) nettoyé(s).', $cleaned));

            return Command::SUCCESS;
        }

        if ($force) {
            $this->seeder->purge();
            $io->writeln('Anciennes données de test supprimées.');
        } elseif ($this->seeder->isSeeded()) {
            $io->warning('Les données de test existent déjà. Utilisez --force pour regénérer ou --purge pour supprimer.');

            return Command::SUCCESS;
        }

        $cleaned = $this->seeder->stripTestMarkersFromDatabase();
        if ($cleaned > 0) {
            $io->writeln(sprintf('%d champ(s) nettoyé(s) ([TEST] retiré).', $cleaned));
        }

        if ($this->cityRepository->count([]) === 0) {
            $io->warning('Aucune ville en base. Exécutez d\'abord : php bin/console app:import-cities');
        }

        $result = $this->seeder->seed();

        $io->title('Données de test créées');

        $io->section('Comptes de connexion');
        $io->table(
            ['Rôle', 'Email', 'Mot de passe'],
            [
                ['Client', $result['users']['client']['email'], $result['users']['client']['password']],
                ['Superviseur', $result['users']['staff']['email'], $result['users']['staff']['password']],
            ]
        );

        $io->section('Résumé par module');
        $io->listing([
            sprintf('Colis : %d scénarios (états, statuts, COD/CRBT, stock, retour)', $result['colis']),
            sprintf('Ramassage : %d demandes (pending, confirmed, picked_up, cancelled + stock)', $result['pickups']),
            sprintf('Bon de livraison : %d bons (enregistré + annulé)', $result['bons']),
            sprintf('Retour : %d demandes (pending, processing, received, cancelled)', $result['returns']),
            sprintf('Stock : %d produits, %d mouvements (draft → cancelled)', $result['stock']['products'], $result['stock']['movements']),
            sprintf('Suivi WhatsApp : %d modèles (actif + inactif)', $result['whatsapp_templates']),
            'Facturation CRBT : auto-généré (en attente, disponible, payé)',
        ]);

        $io->section('Pages à tester');
        $io->listing([
            '/colis — liste principale',
            '/colis/pickup — colis en attente ramassage',
            '/colis/retour — colis retournés',
            '/ramassage/planning — planning ramassage',
            '/bon-livraison — bons de livraison',
            '/retour/demandes — demandes de retour',
            '/retour/bons — bons de retour (connecté en client test)',
            '/facturation/crbt — liste CRBT',
            '/stock/produits — produits stock',
            '/stock/entree — entrées stock',
            '/suivi/modele-whatsapp — modèles WhatsApp',
            '/suivi/changement-destinataire — changement destinataire',
            '/profile — profil client (RIB, retour configurés)',
        ]);

        $io->success('Jeu de données de test prêt.');

        return Command::SUCCESS;
    }
}
