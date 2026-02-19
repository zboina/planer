<?php

namespace Planer\PlanerBundle\EventSubscriber;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InstallCheckSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onConsoleCommand',
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $commandName = $event->getCommand()?->getName();

        // Pokaż komunikat tylko przy cache:clear lub planer:* (poza planer:install)
        if ($commandName === 'planer:install') {
            return;
        }

        if ($commandName !== 'cache:clear' && !str_starts_with((string) $commandName, 'planer:')) {
            return;
        }

        if ($this->isDataInstalled()) {
            return;
        }

        $io = new SymfonyStyle($event->getInput(), $event->getOutput());
        $io->newLine();
        $io->note([
            'Planer Bundle: dane słownikowe nie zostały jeszcze zainstalowane.',
            '',
            'Uruchom komendy:',
            '  1. php bin/console doctrine:migrations:migrate',
            '  2. php bin/console planer:install',
            '',
            'Komenda planer:install zainstaluje:',
            '  • Typy podań (urlop, czas wolny)',
            '  • Rodzaje urlopów (wypoczynkowy, okolicznościowy, na żądanie, odbiór nadgodzin)',
            '  • Szablony podań HTML (urlop, praca zdalna, opieka nad dzieckiem)',
            '  • Typy zmian (1 zmiana, 2 zmiana, urlop, praca zdalna, chorobowe, itp.)',
        ]);
    }

    private function isDataInstalled(): bool
    {
        try {
            $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM planer_typ_zmiany');

            return $count > 0;
        } catch (\Throwable) {
            // Tabela nie istnieje — migracje jeszcze nie uruchomione
            return false;
        }
    }
}
