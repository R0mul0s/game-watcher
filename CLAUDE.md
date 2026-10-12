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
Kurzy: ČNB (textový lístek bez klíče, R14).

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
12. **Limit ITAD je 100 požadavků za 5 minut pro celý klíč** — nic nesmí volat ITAD v cyklu po hrách, když jde dávka (prices až 200 her), a hledání jde přes cache (R15)
13. **Cache neumí objekty** (`cache.serializable_classes = false`) — do cache jen pole (`ItadGame::toArray()`), jinak se vrátí `__PHP_Incomplete_Class`
14. **Hlášky po akci přes `Inertia::flash()`** a ve Vue `page.flash` (komponenta `FlashMessage` v `AppLayout`), ne `session()->flash()` (R16)
15. **V testech `Http::fake()` nepřebije dřívější podvrh** — všechna API přes `fakeApis()`, výpadek zdroje `fakeApis([… => apiError(503)])`; vlastní funkce se nesmí jmenovat `fixture()` (má ji Pest)
16. **Částky v PHP jen jako `Money` (setiny, int)** — žádný float; převod měn jen `ExchangeRates`, řazení a „nejlevněji“ jen `PriceBoard` (R14, R18). Hra pro `PriceBoard` musí mít načtené `currentPrices` a `priceLows` (jinak dotazy v cyklu přes karty)
17. **Klíč gg.deals je v URL** (API jinak neumí) — zprávy do logu jdou jen přes `GgDealsClient::fail()`, který klíč maže; výjimku s původní výjimkou (a URL) nikdy nevyhazovat ani nereportovat (R18)
18. **Cron URL `/cron/ceny` je mimo skupinu `web`** (`routes/cron.php`) — bez session a CSRF; nový periodický krok patří do `CronController` jako další nezávislý krok (R19)
19. **PlayStation Store se čte ze stránky produktu** (`PlayStationPage`, JSON skripty `env:*`), ne z API — parser při změně formátu vrací null, nikdy neuloží odhad. Odkaz od uživatele jen přes `PlayStationUrl::parse()` (jediné ověření hostitele, SSRF). Cena produktu je v `current_prices` pod `psn-<id propojení>`; odpojení maže i historii (R20)
20. **Cena s PS Plus ≠ katalog PS Plus** — `member_amount` je cena pro předplatitele, `member_included` znamená „zdarma v PS Plus Extra“; „mám PS Plus“ ve filtrech počítá jen `member_amount` (R20, R21)
21. **Filtry přehledu filtruje server** (`WatchlistView` nad kartami z `PriceBoard`) a stav je v adrese — nový filtr = parametr v `DashboardRequest::filters()`, pole ve `WatchlistFilters` a podmínka ve `WatchlistView`, ne filtrování ve Vue (R21)
22. **Exkluzivity PlayStationu jsou v ITAD jen zástupný záznam** (bez druhu, obrázků a obchodů) — `games/search` je nevrací, hledání proto volá i `lookupTitle()`; obrázky, datum vydání a vydavatele jim doplní připojení PS Store (jen prázdné údaje, R22). Druhy tlačítek v `PlayStationPage` jsou vyjmenované — nový druh (např. zkušební verze) se nesmí započítat jako cena
23. **Obchody ve výsledcích hledání zjišťuje jeden dávkový dotaz na ceny** (`DescribeAvailability`, cache jako hledání) — nikdy dotaz po hrách; výpadek vrátí `null` a hledání se ukáže bez obchodů. PlayStation se pozná jen z připojeného produktu v katalogu (R23)
24. **Hlídaná platforma patří sledování** (`watchlist_items.platform`, NULL = všude, R24) — cenu karty počítej přes `PriceFilter::restrictedTo()`, ne vlastním filtrováním; „jen PlayStation“ nepočítá obchody s klíči. Odkaz z PS Store v hledání jde přes `SearchByPlayStationLink` (název produktu → ITAD), produkt z odkazu jen přes `FindPlayStationProduct`. Hra je ve výsledcích „na PlayStationu“ podle stránky na playstation.com (`FindPlayStationGamePages`, HEAD v cache) — nikdy po hrách bez cache
25. **Žánry a režimy jsou ze Steamu, počet hráčů z PS Store** (R25) — `games.genres` / `games.modes` plní jen `RefreshGameDetails` (přidání hry a cron, malé dávky kvůli limitu Steamu), `store_listings.local_players` připojení a obnova ceny PS. Režimy hry počítej přes `Game::playModes()` (Steam + PS Store, potřebuje načtené `storeListings`), filtr hráčů přes `PlayerFilter::matches()`
26. **Upozornění vyhodnocuje jen `PriceWatch`** (cíl / historické minimum, cena včetně klíčů a s PS Plus podle účtu) a rozesílá `SendPriceAlerts` v cron URL — synchronně, `PriceAlertMail` nesmí implementovat `ShouldQueue`. Stav „už upozorněno“ je poslední upozorněná cena (`notified_amount`), mění se jen po doručení (R26). Při úpravě stavu v testech pozor na zastaralý model — změna na `null` u modelu, který `null` už měl, se neuloží
27. **Ceny v textech mimo stránku jen přes `MoneyFormatter`** (bez Intl — data ICU pro češtinu na serveru chybět mohou). Odběry Web Push jen přes `PushEndpoint` (známé push služby, SSRF) a patří účtu; `/sw.js` a `/manifest.webmanifest` jsou bez přihlášení (R27)
28. **Graf ceny počítá jen `PriceChart`** (R28) — historie ITAD (`games/history/v2`) přes cache, nikdy v cyklu po hrách; detail ji dostává jako odložený prop (`Inertia::defer`). ECharts registruj jen v `resources/js/lib/echarts.js` a komponentu grafu importuj asynchronně, ať knihovna není v hlavním bloku

## Jazyk

| Co | Jazyk |
|---|---|
| Identifikátory v kódu — třídy, metody, sloupce, tabulky | **anglicky** (`Game`, `current_prices`) |
| Komentáře, PHPDoc, dokumentace, commit zprávy | **česky** |
| Uživatelské texty | **česky, vždy přes `lang/cs/app.php`** — ve Vue přes `useTranslations()` |
| URL | **česky** (`/prihlaseni`, `/odhlaseni`) |

Commity mají předmět „Oblast: co se změnilo" a tělo s tím, co a proč — viz
guidelines, sekce 8.
