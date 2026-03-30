<?php

namespace App\Command;

use App\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-cities',
    description: 'Imports a list of cities into the database',
)]
class ImportCitiesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $citiesRaw = <<<EOT
Marrakech
Meknes
Tnine Mhaya
Ain Taoujdate
Sabaa Aiyoun
Boufakrane
El Hajeb
Bouderbala
Agourai
Boujdour
Tetouan
Martil
M Diq
Fnideq
Errachidia
Oujda
Nador
Selouane
Al Aaroui
Zaio
Driouch
Khouribga
Oued Zem
Boulanouar
Boujniba
Bir Mezoui
Khemisset
Tiflet
Mohammedia
Ain Harrouda
Dakhla
Kenitra
Sidi Taibi
Sidi Yahya El Gharb
Mechra Bel Ksiri
Sidi Sliman
Sidi Kacem
Tan Tan
Guelmim
Bouizakarne
Sidi Ifni
Rabat
Sale
Sale El Jadida
Temara
Tamesna
Skhirat
Ouarzazat
Ait Zineb
Tajda
Timdline
Tifoultoute
Ifran
Azrou
Agadir
Inzegane
Dcheira
Ait Meloul
Sidi Bibi
Taghazoute
Fes
Oulad Ettayeb
Essmara
Berrchid
Deroua
Nouaceur
Had Soualem
Al Hoceima
Ajdir
Ait Kamra
Imzouren
Youssoufia
Essaouira
Ounagha
Had Draa
Mejji
Ghazoua
Tanger
Mrirt
Khenifra
Ben Guerir
Taroudannt
Oulad Teima
Ait Laaza
Aoulouz
Taliouine
Sebt El Guerdane
Berkane
Saidia
Ras El Ma
Bni Drar
Ahfir
Aklim
Zeghanghane
Farkhana
Taza
Guercif
Tiznit
Safi
Had Hrara
Sebt Gzoula
Larache
Ksar El Kebir
Chefchaouen
Sidi Bernoussi
Casablanca
Bouskoura
Dar Bouaza
Mediouna
El Kelaa Des Sraghna
El Jadida
Azemmour
Sidi Bouzid
Moulay Abdellah
Oualidia
Sidi Bennour
Beni Ansar
Laayoune
Beni Mellal
Fquih Ben Salah
Sebt Oulad Nemma
Afourer
Ouled Yaiche
El Ksiba
Ain Aouda
Tamansourt
Benslimane
Kasbah Tadla
Chemaia
Ourika
Tahannaout
Ait Ourir
Oudaya
Chichaoua
Assilah
Bouznika
Sefrou
Taourirt
Chouiter
Sidi Allal el Bahraoui
Ouazzane
Harhoura
Laattaouia
Rissani
Arfoud
Merzouga
Taddart
Bni Bouayach
Azilal
Ouaouizeght
Bejaad
Tinghir
Boumalne Dades
Kalaat MGouna
Alnif
Taounate
Awrir
Ait amira
Belfaa
Anza
BAB TAZA
Bab berred
Midelt
Laayoun Cherqia
Zagora
Tamegroute
Tagomite
Mhamid Lghezlane
Agdz
Nkob
Tazzarine
Errich
Missour
Boumia
Tinjdad
Gulmima
Sidi Bouknadel
IMINTANOUTE
DEMNATE
Ouad Laou
Cabo Negro
Khmiss Zemamera
Sidi Smail
Oulad Fraj
Sidi Rehal
Assa
Boudnib
Ben Ahmed
Sidi Bou Othmane
Souk Elarbaa Du Gharb
AZLA
MARINA
AIN ATIG
MERS LKHIR
SIDI YAHYA ZAIR
JORF SFER
BIR JDID
MIDAR
BENTAYAB
EL GARA
SETTAT
BNI OUKIL
Jemaat Shaim
BENI KLA
TIKIWINE
TIZI OUSLI 
TAHLA
EL CAP
Al Rahma
BOUKIDAN
SKHOUR RHAMNA
IMOUZAR KANDAR
OUED AMLIL
Biougra
Mensouria
Tighsalin 
Jerada
laayoune-oujda
kKerrouchen
Tit Mellil
Oulad berhili
 Tata
moulay yacoub 
Ait ishak 
ighrem laalam
SIDI JABER
Bouarfa
Figuig
Ksar sghir
Oulad ayad
Dar oulad zidoune 
zaouiat cheikh
laouamra
zoumi
Aglmous
Ait Addou
Houara
Mireleft
Tilmia
Ait Marghade
Taadadati
Ait Karmas
Bab Attoutchy
Toumliwana
Tiidrinas
Ait Hanik
Illigha
Tadaout
Tanout
Battou
Takchawa
Lqliaa
Madagh
Regada
Jaadar
Bouarg
ARkman
Teztoutin
Dar El kebdani
Taouima
Ihdaden
Crona
Ouled Settout
Boudinar
Anwal
Amzarou
Mariouari
Bouhlou
Aknoul
Bourd
Sidi Ali Bourakba
Ain Hamra
Hed Ouled Zbir
Kaldman
Issagen
Targist
Tala youssef
Souani
Hicham Azghar
Debdou
Tendrara
Bergem
Cafe Maure
LAMRIS
Naima
Moulay Bousselham
Khenichet
Lalla Mimouna
Ain Beni Mathar
Tamaris
Outat El Haj
El Haj Kaddour
Oued Jdida
El Haouzia
Douar Tikni
Mazagan beach
SIDI ALI
Msawer Rasso
Tnin Chtouka
Oulad Amrane
Sidi Abed
Ouled Ghanem
Oulad Si Bouhya
Riouni
Laaounate
Hed laaounate
Beni Hilal
Jemaat Metal
Sebt El Maarif
Touilaate
Tnine El Ghiate
Dar Si Aissa
Souiria
El Beddouza
Laakarta
Tamelalt
El Bazzaza
Ouled Said El Oued
Oulad Youssef
Oulad Smail
Ait Rbaa
Ait Ali
Adouz
Tagzert
Foum El-Ansar
Tanougha
Oulad Mbarek
Oulad Moussa
Laayayta - Ouled Gnaou
Hed Bradia
Oulad Ali
Oulad Driss
El Khelfia
Had Boumoussa
Timoulilte
Foum Oudi
Beni Ayat
El Kebab
Ouaoumana
Kef en Nsour
Hattane
Ouled Gouaouch
Tachrafat
Drarga
Tamraght
Massa
Houara - Agadir
Tafraoute
Aglou
El Maader El Kabir
Arbaa Sahel
Idaousmlal
Boulemane
Amzmiz
Lagouassem
SIDI ABDELLAH GHIAT
SIDI MOUSSA
AGHMAT
Ouahat Sidi Brahim
Belaaguid
Oulad Yahya
Douar Bouaazza
Souihla
SIDI ZOUIN
Douar Sultan
Moulay Brahim
Sidi Mokhtar
Mzoudia
Ouled Hassoune
Ajdir (région taza)
Karia Be Mohammed
Skoura
Dkhissa
Moulay driss zerhoun
Ain Karma
Jemâa El Houdrane
Ain Johra
Bhalil
Elmnzel 
sidi allal tai
EOT;

        $cities = array_unique(array_filter(array_map('trim', explode("\n", $citiesRaw))));
        sort($cities);

        $count = 0;
        foreach ($cities as $cityName) {
            // Check if city already exists
            $existing = $this->entityManager->getRepository(City::class)->findOneBy(['name' => $cityName]);
            if (!$existing) {
                $city = new City();
                $city->setName($cityName);
                $this->entityManager->persist($city);
                $count++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully imported %d cities.', $count));

        return Command::SUCCESS;
    }
}
