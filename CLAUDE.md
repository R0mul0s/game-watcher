# Game Watcher

Soukromé hlídání cen her napříč obchody — Steam, PlayStation Store, Epic, Ubisoft,
Battle.net a obchody s klíči (Kinguin, G2A, Loaded, Eneba). Laravel na sdíleném PHP
hostingu, vše za přihlášením, účty zakládá správce. Postavené podle projektu
Počasí (`C:\Github\Počasí`) — stejný stack, hosting a způsob práce (R1).

## Dokumentace

Před prací na projektu si přečti:

- **[docs/PLAN.md](docs/PLAN.md)** — zadání: zdroje cen a výsledek průzkumu, datový model, toky dat, etapy, otevřené otázky, log rozhodnutí
- **[docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md)** — závazná pravidla pro psaní kódu
- **[docs/TODO.md](docs/TODO.md)** — odložené úkoly a nápady
- **[deploy/DEPLOYMENT.md](deploy/DEPLOYMENT.md)** — nasazení na hosting
- **[docs/research/check-stores.php](docs/research/check-stores.php)** — diagnostika dostupnosti obchodů z hostingu (R7)

Všechno je závazné. Když se rozhodnutí změní, **aktualizuj příslušný dokument
ve stejném commitu** jako kód. Nové rozhodnutí patří do logu v PLAN.md (další
volné číslo R…).

## Prostředí

Projekt běží výhradně v Dockeru — PHP, Composer ani Node se lokálně neinstalují.

```bash
docker compose up -d
```

| Služba | Adresa |
|---|---|
| Aplikace | http://localhost:54730 |
| MariaDB | localhost:54731 (`games` / `games`), testy v `games_test` |
| Vite dev server | http://localhost:54732 (jen při `npm run dev`) |

Porty 5471x patří Počasí a 5472x Letákům — projekty běží vedle sebe.

Artisan, composer i npm se pouštějí v kontejneru:
```bash
docker compose exec app php artisan migrate
docker compose exec app composer install
docker compose exec app npm run build   # produkční build assetů
docker compose exec app npm run dev     # watch s HMR
```

Účet (registrace v aplikaci není, R4; heslo se zadává skrytě):
```bash
docker compose exec app php artisan games:create-user roman@example.com --name=Roman
docker compose exec app php artisan games:create-user roman@example.com --sql   # jen SQL pro produkci
```

Nová routa „neexistuje"? V `bootstrap/cache` zůstala cache — `docker compose exec app php artisan optimize:clear`.

## Kontrola kvality

**Pusť ručně a musí projít všechno**, než se něco commitne:
```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app npm run build   # při změně JS, Vue nebo SCSS
```

Testy běží proti MariaDB `games_test`, ne SQLite.

## Produkce

Shared hosting Websupport, `game-watcher.rhsoft.cz` (R12), Apache 2.4 + PHP 8.4,
MariaDB 11.4. **Není tam SSH ani composer** — nic z `php artisan` se na produkci nespustí. Z toho plyne:

- **Každá nová migrace potřebuje SQL skript** `deploy/migrations-<datum>-<popis>.sql`
  (opakovatelný, včetně zápisu do tabulky `migrations`) ve stejném commitu. Nabídni ho sám.
- **Účty jen SQL skriptem** z `games:create-user --sql` — hash hesla, nikdy heslo.
- **Žádná fronta, scheduler ani démon** — nic nesmí implementovat `ShouldQueue`.
  Stahování cen bude volat cron hostingu přes URL s tokenem (etapa 3).
- Nasazení: `deploy\build-upload.ps1` + FTP. Po dokončení změny připomeň, jestli
  vyžaduje SQL skript, nový build assetů nebo novou proměnnou v `.env`.

## Stack

PHP 8.4 · Laravel 13 · Inertia 3 · Vue 3 · SCSS (BEM + CSS tokeny) · Fontsource (Oxanium,
Chakra Petch, JetBrains Mono) · MariaDB 11.4 · Pest 4 · Larastan · Pint.
Zdroje cen: IsThereAnyDeal API, gg.deals API, přímo PlayStation Store, Kinguin, Eneba (R9).

## Nejčastější zdroje chyb v tomhle projektu

1. **Z hostingu nejsou dostupné Epic, G2A ani Loaded** (Cloudflare/Akamai na datacentrové IP, R7) — že něco jde z domácího PC, neznamená, že to půjde z hostingu. Nový přímý zdroj nejdřív ověřit na hostingu (`docs/research/check-stores.php`)
2. **Identita hry je ITAD ID** (`games.itad_id`, R10) — ne název, ne Steam App ID (DLC a hry mimo Steam ho nemají)
3. **Měny se liší podle zdroje** — ITAD a gg.deals EUR, PlayStation CZK; částky jsou `decimal` + `currency`, nikdy je nesčítat ani nesrovnávat bez převodu; zobrazení v CZK/EUR převádí kurz ČNB až při zobrazení (R14)
4. **Zdroje vrací ceny různě** — Kinguin v centech a `1000000` = žádná nabídka, PlayStation `discountedValue` v haléřích, Eneba `lowestPrice` jen v měnách podle země IP
5. **Obchody s klíči: nehledat produkt podle názvu** — varianty regionů a „Mystery“ klíče dávají nesmyslné ceny; produkt se připojí URL (R10)
6. **gg.deals dává jen souhrn** (nejnižší cena obchodů s klíči), ne cenu G2A nebo Loaded — a jeho data musí mít na stránce odkaz na gg.deals (R9)
7. **Testy nesmí volat skutečná API ani obchody** — `Http::preventStrayRequests()`; odpovědi ze `tests/Fixtures/` (skutečné, zachycené)
8. **API klíče jen v `.env`**, nikdy v logu, odpovědi ani frontendu; URL zadané uživatelem jen s povoleným hostitelem (SSRF)
9. **Vzhled je jen tmavý** (R6) a vše jde přes tokeny — neon, záře i délky animací; animace jen v mixinu `with-motion`
10. **Session je v cookie** (R5) — tabulka `sessions` neexistuje, `SESSION_DRIVER=database` by na produkci spadl
11. **Nepřidávej závislost, kterou hosting nemá** — Redis, frontu, démona, headless prohlížeč (R2, R8)

## Jazyk

| Co | Jazyk |
|---|---|
| Identifikátory v kódu — třídy, metody, sloupce, tabulky | **anglicky** (`Game`, `current_prices`) |
| Komentáře, PHPDoc, dokumentace, commit zprávy | **česky** |
| Uživatelské texty | **česky, vždy přes `lang/cs/app.php`** — ve Vue přes `useTranslations()` |
| URL | **česky** (`/prihlaseni`, `/odhlaseni`) |

Commity mají předmět „Oblast: co se změnilo" a tělo s tím, co a proč — viz
guidelines, sekce 8.
