<?php

namespace Planer\PlanerBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Planer\PlanerBundle\Entity\RodzajUrlopu;
use Planer\PlanerBundle\Entity\SzablonPodania;
use Planer\PlanerBundle\Entity\TypPodania;
use Planer\PlanerBundle\Entity\TypZmiany;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'planer:install',
    description: 'Instaluje domyślne dane słownikowe bundla Planer (szablony podań, typy podań, rodzaje urlopów, typy zmian)',
)]
class PlanerInstallCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Nadpisz istniejące dane');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('Instalacja danych słownikowych Planer');

        $this->installTypyPodania($io, $force);
        $this->installRodzajeUrlopu($io, $force);
        $this->installSzablonyPodan($io, $force);

        // Flush szablony first — typy zmian need references to them
        $this->em->flush();

        $this->installTypyZmian($io, $force);

        $this->em->flush();

        $io->success('Instalacja zakończona.');

        return Command::SUCCESS;
    }

    // ──────────────────────────────────────
    //  TYPY PODAŃ
    // ──────────────────────────────────────

    private function installTypyPodania(SymfonyStyle $io, bool $force): void
    {
        $repo = $this->em->getRepository(TypPodania::class);
        $existing = $repo->count([]);

        if ($existing > 0 && !$force) {
            $io->note(sprintf('Typy podań: pominięto (istnieje %d rekordów). Użyj --force aby nadpisać.', $existing));
            return;
        }

        $data = [
            ['nazwa' => 'urlopu', 'kolejnosc' => 1],
            ['nazwa' => 'czasu wolnego od pracy', 'kolejnosc' => 2],
        ];

        $count = 0;
        foreach ($data as $row) {
            $found = $repo->findOneBy(['nazwa' => $row['nazwa']]);
            if ($found) {
                if ($force) {
                    $found->setKolejnosc($row['kolejnosc']);
                }
                continue;
            }

            $entity = new TypPodania();
            $entity->setNazwa($row['nazwa']);
            $entity->setKolejnosc($row['kolejnosc']);
            $this->em->persist($entity);
            $count++;
        }

        $io->writeln(sprintf('  Typy podań: dodano <info>%d</info> rekordów', $count));
    }

    // ──────────────────────────────────────
    //  RODZAJE URLOPÓW
    // ──────────────────────────────────────

    private function installRodzajeUrlopu(SymfonyStyle $io, bool $force): void
    {
        $repo = $this->em->getRepository(RodzajUrlopu::class);
        $existing = $repo->count([]);

        if ($existing > 0 && !$force) {
            $io->note(sprintf('Rodzaje urlopów: pominięto (istnieje %d rekordów). Użyj --force aby nadpisać.', $existing));
            return;
        }

        $data = [
            ['nazwa' => 'wypoczynkowego', 'kolejnosc' => 1],
            ['nazwa' => 'okolicznościowego', 'kolejnosc' => 2],
            ['nazwa' => 'na żądanie', 'kolejnosc' => 3],
            ['nazwa' => 'odbiór nadgodzin', 'kolejnosc' => 4],
        ];

        $count = 0;
        foreach ($data as $row) {
            $found = $repo->findOneBy(['nazwa' => $row['nazwa']]);
            if ($found) {
                if ($force) {
                    $found->setKolejnosc($row['kolejnosc']);
                }
                continue;
            }

            $entity = new RodzajUrlopu();
            $entity->setNazwa($row['nazwa']);
            $entity->setKolejnosc($row['kolejnosc']);
            $this->em->persist($entity);
            $count++;
        }

        $io->writeln(sprintf('  Rodzaje urlopów: dodano <info>%d</info> rekordów', $count));
    }

    // ──────────────────────────────────────
    //  SZABLONY PODAŃ
    // ──────────────────────────────────────

    private function installSzablonyPodan(SymfonyStyle $io, bool $force): void
    {
        $repo = $this->em->getRepository(SzablonPodania::class);
        $existing = $repo->count([]);

        if ($existing > 0 && !$force) {
            $io->note(sprintf('Szablony podań: pominięto (istnieje %d rekordów). Użyj --force aby nadpisać.', $existing));
            return;
        }

        $dataDir = dirname(__DIR__, 2) . '/data';

        $szablony = [
            [
                'nazwa' => 'Podanie o urlop',
                'pola' => ['typ_podania', 'rodzaj_urlopu', 'zastepca', 'telefon', 'uzasadnienie', 'podpis', 'adres'],
                'file' => 'szablon_urlop.html',
            ],
            [
                'nazwa' => 'Wniosek o pracę zdalną',
                'pola' => ['podpis'],
                'file' => 'szablon_praca_zdalna.html',
            ],
            [
                'nazwa' => 'Opieka nad dzieckiem (art. 188 KP)',
                'pola' => ['podpis'],
                'file' => 'szablon_opieka.html',
            ],
        ];

        $count = 0;
        foreach ($szablony as $row) {
            $found = $repo->findOneBy(['nazwa' => $row['nazwa']]);

            $htmlFile = $dataDir . '/' . $row['file'];
            if (!file_exists($htmlFile)) {
                $io->warning(sprintf('Brak pliku szablonu: %s', $row['file']));
                continue;
            }
            $html = file_get_contents($htmlFile);

            if ($found) {
                if ($force) {
                    $found->setTrescHtml($html);
                    $found->setPolaFormularza($row['pola']);
                    $found->setUpdatedAt(new \DateTime());
                }
                continue;
            }

            $entity = new SzablonPodania();
            $entity->setNazwa($row['nazwa']);
            $entity->setTrescHtml($html);
            $entity->setPolaFormularza($row['pola']);
            $entity->setAktywny(true);
            $this->em->persist($entity);
            $count++;
        }

        $io->writeln(sprintf('  Szablony podań: dodano <info>%d</info> rekordów', $count));
    }

    // ──────────────────────────────────────
    //  TYPY ZMIAN
    // ──────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDefaultTypyZmian(): array
    {
        return [
            [
                'nazwa' => '1 zmiana',
                'skrot' => '1',
                'kolor' => '#b6dafc',
                'kolejnosc' => 0,
                'klawisz' => '1',
                'tylkoGlowny' => false,
                'szablon_nazwa' => null,
            ],
            [
                'nazwa' => '2 zmiana',
                'skrot' => '2',
                'kolor' => '#b8b8b7',
                'kolejnosc' => 1,
                'klawisz' => '2',
                'tylkoGlowny' => false,
                'szablon_nazwa' => null,
            ],
            [
                'nazwa' => 'Praca zdalna',
                'skrot' => 'PZ',
                'kolor' => '#0f8055',
                'kolejnosc' => 2,
                'klawisz' => 'Z',
                'tylkoGlowny' => false,
                'szablon_nazwa' => 'Wniosek o pracę zdalną',
            ],
            [
                'nazwa' => 'Urlop',
                'skrot' => 'U',
                'kolor' => '#f2d545',
                'kolejnosc' => 3,
                'klawisz' => 'U',
                'tylkoGlowny' => true,
                'szablon_nazwa' => 'Podanie o urlop',
            ],
            [
                'nazwa' => 'Wolne',
                'skrot' => 'W',
                'kolor' => '#01d065',
                'kolejnosc' => 4,
                'klawisz' => 'W',
                'tylkoGlowny' => false,
                'szablon_nazwa' => null,
            ],
            [
                'nazwa' => 'Odbiór nadgodzin',
                'skrot' => 'ON',
                'kolor' => '#694cae',
                'kolejnosc' => 5,
                'klawisz' => 'N',
                'tylkoGlowny' => false,
                'szablon_nazwa' => null,
            ],
            [
                'nazwa' => 'Chorobowe',
                'skrot' => 'L4',
                'kolor' => '#f44336',
                'kolejnosc' => 6,
                'klawisz' => 'L',
                'tylkoGlowny' => true,
                'szablon_nazwa' => null,
            ],
            [
                'nazwa' => 'Opieka nad dzieckiem',
                'skrot' => 'OD',
                'kolor' => '#f74a7e',
                'kolejnosc' => 7,
                'klawisz' => 'D',
                'tylkoGlowny' => true,
                'szablon_nazwa' => 'Opieka nad dzieckiem (art. 188 KP)',
            ],
        ];
    }

    private function installTypyZmian(SymfonyStyle $io, bool $force): void
    {
        $repo = $this->em->getRepository(TypZmiany::class);
        $existing = $repo->count([]);

        if ($existing > 0 && !$force) {
            $io->note(sprintf('Typy zmian: pominięto (istnieje %d rekordów). Użyj --force aby nadpisać.', $existing));
            return;
        }

        $defaults = $this->getDefaultTypyZmian();

        // Filter out already existing (by skrot)
        $toInstall = [];
        foreach ($defaults as $row) {
            $found = $repo->findOneBy(['skrot' => $row['skrot']]);
            if ($found) {
                continue;
            }
            $toInstall[] = $row;
        }

        if (empty($toInstall)) {
            $io->writeln('  Typy zmian: wszystkie już istnieją');
            return;
        }

        // Show table
        $io->section('Typy zmian do zainstalowania');

        $tableData = [];
        foreach ($toInstall as $i => $row) {
            $tableData[] = [
                $i + 1,
                $row['nazwa'],
                $row['skrot'],
                $row['kolor'],
                $row['klawisz'] ?? '-',
                $row['tylkoGlowny'] ? 'Tak' : 'Nie',
                $row['szablon_nazwa'] ?? '-',
            ];
        }

        $table = new Table($io);
        $table->setHeaders(['#', 'Nazwa', 'Skrót', 'Kolor', 'Klawisz', 'Tylko główny', 'Szablon podania']);
        $table->setRows($tableData);
        $table->render();
        $io->newLine();

        // Ask: all or one-by-one
        $mode = $io->choice(
            'Jak zainstalować typy zmian?',
            [
                'all' => 'Zainstaluj wszystkie',
                'pick' => 'Wybieraj po kolei',
                'skip' => 'Pomiń — nie instaluj żadnego',
            ],
            'all',
        );

        if ($mode === 'skip') {
            $io->writeln('  Typy zmian: pominięto');
            return;
        }

        $szablonRepo = $this->em->getRepository(SzablonPodania::class);
        $count = 0;

        foreach ($toInstall as $row) {
            if ($mode === 'pick') {
                $label = sprintf('%s [%s] kolor: %s', $row['nazwa'], $row['skrot'], $row['kolor']);
                if ($row['szablon_nazwa']) {
                    $label .= sprintf(' → szablon: %s', $row['szablon_nazwa']);
                }
                if (!$io->confirm(sprintf('Dodać typ "%s"?', $label), true)) {
                    continue;
                }
            }

            $entity = new TypZmiany();
            $entity->setNazwa($row['nazwa']);
            $entity->setSkrot($row['skrot']);
            $entity->setKolor($row['kolor']);
            $entity->setKolejnosc($row['kolejnosc']);
            $entity->setAktywny(true);
            $entity->setTylkoGlowny($row['tylkoGlowny']);

            if ($row['klawisz'] ?? null) {
                $entity->setSkrotKlawiaturowy($row['klawisz']);
            }

            if ($row['szablon_nazwa']) {
                $szablon = $szablonRepo->findOneBy(['nazwa' => $row['szablon_nazwa']]);
                if ($szablon) {
                    $entity->setSzablon($szablon);
                    $entity->setSzablonPodania('custom');
                }
            }

            $this->em->persist($entity);
            $count++;
        }

        $io->writeln(sprintf('  Typy zmian: dodano <info>%d</info> rekordów', $count));
    }
}
