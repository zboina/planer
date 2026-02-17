# PlanerBundle

Grafik pracy (planer zmianowy) — Symfony Bundle.

Funkcje: grafik zmianowy, departamenty, podania urlopowe, dni wolne firmy, generowanie PDF.

## Wymagania

- PHP >= 8.2
- Symfony >= 7.0
- Doctrine ORM >= 3.0
- PostgreSQL

## Instalacja

### 1. Composer

```bash
composer require planer/planer-bundle
```

### 2. Rejestracja bundla

Dodaj do `config/bundles.php`:

```php
return [
    // ...
    Planer\PlanerBundle\PlanerBundle::class => ['all' => true],
];
```

### 3. Konfiguracja

Utwórz plik `config/packages/planer.yaml`:

```yaml
planer:
    user_class: App\Entity\User
    base_template: 'base.html.twig'
    firma_nazwa: 'Nazwa Firmy Sp. z o.o.'
    firma_adres: "ul. Przykładowa 1\n00-000 Warszawa"
```

### 4. Routing

Utwórz plik `config/routes/planer.yaml`:

```yaml
planer:
    resource: '@PlanerBundle/config/routes.yaml'
```

### 5. Encja User

Twoja encja `User` musi implementować `Planer\PlanerBundle\Model\PlanerUserInterface`:

```php
use Planer\PlanerBundle\Model\PlanerUserInterface;

class User implements PlanerUserInterface
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adres = null;

    #[ORM\Column(type: 'integer', options: ['default' => 26])]
    private int $iloscDniUrlopuWRoku = 26;

    public function getFullName(): ?string
    {
        // zwraca imię i nazwisko
    }

    public function getAdres(): ?string
    {
        return $this->adres;
    }

    public function setAdres(?string $adres): static
    {
        $this->adres = $adres;
        return $this;
    }

    public function getIloscDniUrlopuWRoku(): int
    {
        return $this->iloscDniUrlopuWRoku;
    }

    public function setIloscDniUrlopuWRoku(int $iloscDniUrlopuWRoku): static
    {
        $this->iloscDniUrlopuWRoku = $iloscDniUrlopuWRoku;
        return $this;
    }
}
```

Kolumny `adres` i `ilosc_dni_urlopu_w_roku` należy dodać do tabeli `user` ręcznie (lub przez migrację w projekcie hosta):

```sql
ALTER TABLE "user" ADD COLUMN adres VARCHAR(255) DEFAULT NULL;
ALTER TABLE "user" ADD COLUMN ilosc_dni_urlopu_w_roku INT NOT NULL DEFAULT 26;
```

### 6. Migracje

Bundle dostarcza skonsolidowaną migrację tworzącą wymagane tabele. Uruchom:

```bash
php bin/console doctrine:migrations:migrate
```

Aby Doctrine widział migracje bundla, dodaj konfigurację w `config/packages/doctrine_migrations.yaml`:

```yaml
doctrine_migrations:
    migrations_paths:
        'Planer\PlanerBundle\Migrations': '@PlanerBundle/migrations'
```

### 7. Asset JS (Stimulus)

Skopiuj controller Stimulus do projektu:

```bash
cp vendor/planer/planer-bundle/assets/controllers/grafik_controller.js assets/controllers/grafik_controller.js
```

Controller wymaga Stimulus i jest używany w widoku grafiku.

## Tabele tworzone przez bundle

| Tabela | Opis |
|---|---|
| `departament` | Departamenty / działy |
| `user_departament` | Przypisanie user-departament (M:N z flagami szef/główny) |
| `typ_zmiany` | Słownik typów zmian (Dzienna, Nocna, itp.) |
| `grafik_wpis` | Wpisy grafiku pracy |
| `dzien_wolny_firmy` | Dni wolne firmy |
| `typ_podania` | Słownik typów podań |
| `rodzaj_urlopu` | Słownik rodzajów urlopu |
| `podanie_urlopowe` | Podania urlopowe |

## Licencja

Proprietary
# planer
