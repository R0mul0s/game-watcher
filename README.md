# Game Watcher

Soukromé **hlídání cen her** napříč obchody — Steam, PlayStation Store, Epic, Ubisoft,
Battle.net a obchody s klíči (Kinguin, G2A, Loaded, Eneba). Na jednom místě je vidět,
kde je hra nejlevnější, jak se cena vyvíjí, a přijde upozornění, když spadne pod zvolenou mez.

| | |
|---|---|
| **Produkce** | [game-watcher.rhsoft.cz](https://game-watcher.rhsoft.cz) — shared hosting Websupport, zatím nenasazeno |
| **Zadání, zdroje cen, rozhodnutí** | [docs/PLAN.md](docs/PLAN.md) |
| **Pravidla pro psaní kódu** | [docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md) |
| **Nasazení** | [deploy/DEPLOYMENT.md](deploy/DEPLOYMENT.md) |
| **Instrukce pro AI agenty** | [CLAUDE.md](CLAUDE.md) |
| **Správce** | Roman Hlaváček |

## Stack

PHP 8.4 · Laravel 13 · Inertia 3 · Vue 3 · SCSS · MariaDB 11.4 · Pest —
podrobně s důvody v [PLAN.md, sekce 3](docs/PLAN.md#3-technologie).
Ceny z [IsThereAnyDeal](https://isthereanydeal.com) a [gg.deals](https://gg.deals) API
a přímo z PlayStation Store, Kinguinu a Eneby.

## Požadavky

**Docker Desktop** a Git — nic dalšího. PHP, Composer i Node běží v kontejneru.
Pro zdroje cen vlastní (bezplatné) API klíče IsThereAnyDeal a gg.deals.

## Rychlý start

```bash
cp .env.example .env
# doplnit do .env: GAMES_ITAD_API_KEY, GAMES_GGDEALS_API_KEY (postup v komentářích .env.example)

docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run build

# účet — registrace v aplikaci není (heslo se zadá skrytě, min. 12 znaků)
docker compose exec app php artisan games:create-user roman@example.com --name=Roman
```

Pak otevři http://localhost:54730 a přihlas se.

### Služby

| Služba | Adresa |
|---|---|
| Aplikace | http://localhost:54730 |
| MariaDB | localhost:54731 (`games` / `games`) |
| Vite dev server | http://localhost:54732 (při `npm run dev`) |

Databáze pro testy `games_test` vzniká automaticky, ale **jen při prvním
startu nad prázdným volume** (`docker/mariadb/init.sql`). Když chybí, smaž
volume (`docker compose down -v`).

## Konfigurace

| Proměnná | Povinná | K čemu |
|---|---|---|
| `GAMES_ITAD_API_KEY` | od etapy 2 | klíč IsThereAnyDeal API — katalog her a ceny oficiálních obchodů (isthereanydeal.com → Developer → My Apps; OAuth není potřeba) |
| `GAMES_GGDEALS_API_KEY` | od etapy 3 | klíč gg.deals API — nejnižší cena obchodů s klíči (nastavení účtu → API; data se zobrazují s odkazem na gg.deals) |
| `GAMES_CRON_TOKEN` | jen produkce | token cron URL `/cron/ceny` (kurzy ČNB, ceny, úklid); prázdný = URL vypnutá |
| `GAMES_ITAD_URL`, `GAMES_GGDEALS_URL`, `GAMES_CNB_URL` | ne | adresy API a kurzovního lístku ČNB, jen když se mají měnit výchozí |
| `GAMES_DISPLAY_TIMEZONE` | ne (`Europe/Prague`) | zóna pro zobrazení a místní den historie cen; data jsou v UTC |

Bez platného `GAMES_ITAD_API_KEY` přidávání her hlásí, že katalog neodpovídá (podrobnost v `storage/logs`).

Ostatní konstanty (limit pokusů o přihlášení, minimální délka hesla, země a region
cen, barva lišty prohlížeče) jsou v `config/games.php`.

## Běžné příkazy

Kontrola kvality před commitem (ručně, CI není — R17) — viz [CODING_GUIDELINES.md, sekce 9](docs/CODING_GUIDELINES.md#9-nástroje-a-kvalita).

```bash
# assety
docker compose exec app npm run dev      # watch s HMR
docker compose exec app npm run build    # produkční build

# účty
docker compose exec app php artisan games:create-user <e-mail> [--name=Jméno]
docker compose exec app php artisan games:create-user <e-mail> --sql   # SQL pro produkci (phpMyAdmin)
```

## Struktura repozitáře

```
app/
  Console/Commands/ artisan příkazy — obálky nad akcemi (games:create-user)
  Domain/Accounts/  účty — CreateUser
  Domain/Itad/      klient IsThereAnyDeal API (katalog, později ceny)
  Domain/Catalog/   katalog her — hledání, převzetí hry z ITAD, data pro stránky
  Domain/Watchlist/ osobní seznam sledovaných her
  Domain/GgDeals/   klient gg.deals API (nejnižší cena obchodů s klíči)
  Domain/Pricing/   ceny — stažení, uložení, příprava pro stránky v měně účtu
  Domain/Currency/  kurzy ČNB a převod CZK / EUR
  Domain/Maintenance/ úklid volaný z cron URL
  Http/             tenké kontrolery, Form Requesty (LoginRequest), sdílená data Inertie
config/games.php    konstanty projektu, katalog (limity hledání) a zdroje cen
database/           migrace
deploy/             build skript, SQL skripty a návod pro nasazení
docker/             PHP, nginx a MariaDB pro vývoj; tools/ = pomocné skripty (ikony)
docs/               zadání, pravidla, TODO; research/ = diagnostika dostupnosti obchodů
lang/cs/app.php     všechny texty
resources/images/   logo.svg — zdroj ikon
resources/js/       Inertia stránky, rozvržení a Vue komponenty
resources/scss/     styly (tokeny, rozvržení, komponenty)
routes/web.php      přihlášení a stránky za přihlášením
routes/cron.php     cron URL /cron/ceny (mimo skupinu web, token)
tests/              Pest — Feature a Unit
```

## Řešení potíží

| Příznak | Příčina a řešení |
|---|---|
| `Route [...] not defined` | cache rout — `php artisan optimize:clear` |
| Testy padají na připojení k databázi | chybí `games_test`, viz *Služby* |
| Na stránce chybí styly nebo písma | nesestavené assety — `npm run build` |
| Přihlášení hlásí „Příliš mnoho pokusů“ | 5 neúspěchů pro e-mail + IP, počkej minutu (`games.auth`) |
| Port 54730 je obsazený | běží jiný projekt — porty jsou v `compose.yaml` |
