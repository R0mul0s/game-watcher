<!--
  Pravidla pro psaní kódu — Game Watcher
  @author Roman Hlaváček
  @created 2026-10-11
-->

# Coding Guidelines — Game Watcher

Tento dokument je závazný pro všechny úpravy v repozitáři. Vychází z pravidel
projektu Počasí (R1 v [PLAN.md](PLAN.md)), upravených na tenhle projekt.
Cílem je udržet projekt **čitelný, bezpečný a dlouhodobě udržovatelný**.
V rozporu mezi „elegancí" a „srozumitelností" volíme srozumitelnost.

Funkční zadání a log rozhodnutí je v [PLAN.md](PLAN.md).

---

## 1. Obecné principy

- **Best practices daného jazyka a frameworku** — vždy. Když něco děláš jinak, musí to mít zdokumentovaný důvod.
- **Žádné magic numbers** — hodnoty patří do konstant, enumů nebo konfigurace (`config/games.php`, `.env`)
- **Než napíšeš novou utilitu, zjisti, jestli už existuje** — v projektu, v Laravelu, v použitých balíčcích nebo v projektu Počasí (odtud je převzaté i18n, build skript, vzory cron URL)
- **YAGNI** — žádné feature flagy a abstrakce pro hypotetické budoucí potřeby
- **Validace na hranici systému** (Form Request, artisan příkaz) — uvnitř doménového kódu věř typům
- **Bezpečnost:** žádný SQL string concat, žádný `eval`, žádný `unserialize` na vstupu zvenčí

---

## 2. Hlavička každého nového souboru

Každý PHP, JS, Vue, SCSS, Blade, SQL a PowerShell soubor začíná hlavičkou
s popisem, autorem a datem vytvoření. Platí to i pro soubory z `make:*` —
generátor hlavičku nepřidá.

**PHP** — hlavička před `declare(strict_types=1)`, prázdný řádek před `@created` doplní Pint:
```php
<?php

/**
 * Krátký popis souboru.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);
```

**JS:**
```js
/**
 * Krátký popis souboru.
 *
 * @author Roman Hlaváček
 * @created 2026-10-11
 */
```

**Vue:**
```vue
<!--
    Krátký popis komponenty.

    @author Roman Hlaváček
    @created 2026-10-11
-->
```

**SCSS:**
```scss
// _panel.scss
// Krátký popis.
//
// @author Roman Hlaváček
// @created 2026-10-11
```

**Blade:** `{{-- … --}}`, **SQL:** `-- …` se stejným obsahem.

---

## 3. PHP & Laravel

### Verze a styl
- **PHP 8.4** — shodné s produkcí
- **`declare(strict_types=1);`** v každém PHP souboru
- **Formátování:** Laravel Pint (preset `laravel`)
- **Statická analýza:** Larastan level 8
- **Naming:** třídy `PascalCase`, modely v jednotném čísle (`Game`); metody a proměnné `camelCase`; URL `kebab-case` a **česky** (`/prihlaseni`, `/hry/{slug}`)

### Architektura

Tenké kontrolery, logika v doméně:

```
Controller / Artisan command   ← orchestrace, validace (Form Request), odpověď
    ↓
Action / doménová třída        ← jeden use-case = jedna třída (CreateUser, RefreshPrices)
    ↓
Eloquent model                 ← perzistence
```

| Složka | Obsah |
|---|---|
| `app/Domain/Accounts` | účty (`Actions/CreateUser`) |
| `app/Domain/Itad` | HTTP klient IsThereAnyDeal (`ItadClient`) a jeho datové třídy (`ItadGame`, `ItadGameInfo`, `ItadGamePrices`, `ItadDeal`) — katalog i ceny oficiálních obchodů |
| `app/Domain/GgDeals` | HTTP klient gg.deals (`GgDealsClient`, `GgDealsPrices`) — nejnižší cena obchodů s klíči; klíč maže z logu (R18) |
| `app/Domain/Currency` | kurzy ČNB — lístek (`CnbRateSheet`), stažení (`Actions/ImportExchangeRates`), převod (`ExchangeRates`, jeden za požadavek) (R14) |
| `app/Domain/Catalog` | katalog her — dotaz (`GameQuery`), hledání (`Actions/SearchGames`), převzetí hry (`Actions/ImportGame`), data pro stránky (`GamePresenter`) |
| `app/Domain/Watchlist` | osobní seznam sledovaných her (`Actions/WatchGame`, `Actions/UnwatchGame`); upozornění v etapě 6 |
| `app/Domain/Pricing` | ceny — částka (`Money`), stažení (`Actions/RefreshPrices`), uložení (`StorePrices`), data pro stránky v měně účtu (`PriceBoard`) (R18); přímé zdroje v etapě 5 |
| `app/Domain/Maintenance` | úklid volaný z cron URL (`PruneExpiredCache`) — hosting nemá scheduler |
| `app/Enums` | výčty (`PriceSource`, `PriceScope`) |
| `app/Http/Controllers` | tenké kontrolery |
| `app/Http/Requests` | Form Requesty (včetně `LoginRequest`) |
| `app/Console/Commands` | Artisan příkazy — jen obálka nad Action |

- **Doménová logika nepatří do kontroleru, commandu ani modelu**
- Výjimka: ověření přihlášení s limitem pokusů je v `LoginRequest::authenticate()` — vzor startovacích sad Laravelu, jde o hranici systému
- Repository třídy nezavádíme — Eloquent stačí
- **Každý zdroj cen je samostatná třída** se stejným rozhraním (vrací ceny v měně zdroje). Výpadek nebo změna formátu jednoho zdroje nesmí shodit ostatní (R9)

### Konfigurace
- Konstanty projektu v **`config/games.php`** s výchozí hodnotou z `.env`
- Do `.env` patří jen infrastruktura a tajemství (DB, API klíče)
- Kód čte `config('games.…')`, nikdy `env()` mimo konfigurační soubory

### Eloquent
- `$fillable` explicitně, nikdy `$guarded = []`
- `casts()` explicitně — datumy jako `immutable_datetime`, peníze jako `decimal:2`, enumy jako enum
- `Model::preventLazyLoading()` mimo produkci (zapnuté v `AppServiceProvider`)
- Žádné raw queries kromě analytických dotazů — a tam vždy parametrizovaně

### Peníze a měny
- **Částky se ukládají v měně zdroje** jako `decimal(10,2)` + sloupec `currency` (ISO 4217) — nikdy `float`
- **Nikdy nesčítat ani neporovnávat částky v různých měnách** bez převodu — zobrazuje se v CZK nebo EUR podle volby účtu, převádí se až při zobrazení denním kurzem ČNB (`exchange_rates`, R14); částka v měně zdroje zůstává uložená beze změny
- **V PHP jsou částky `Money` v setinách** (celé číslo) — z API přes `amountInt` nebo `Money::fromDecimal()` (řetězec, ne float), do DB `toDecimal()`, na stránku `toArray()`. Převod měn jen `ExchangeRates::convert()`, řazení a „nejlevněji“ jen `PriceBoard` — komponenta nic nepřevádí
- Zdroje vrací ceny různě: ITAD `amountInt` (centy), gg.deals řetězec „38.98“, Kinguin v centech (`1000000` = bez nabídky), PlayStation `discountedValue` v haléřích — převod na `Money` na jednom místě v klientovi zdroje

### Externí zdroje a stahování
- **Všechna volání přes HTTP klienta Laravelu** (`Http::`) s timeoutem z konfigurace — jinak nejdou testovat přes `Http::fake()`
- **Klíče API se nikdy nelogují** a nejdou do odpovědí ani do frontendu
- **URL zadané uživatelem** (produkt v obchodě) se ověří proti povolenému hostiteli zdroje (`store.playstation.com`, `www.kinguin.net`, `www.eneba.com`) — server nesmí volat libovolnou adresu (SSRF)
- Přímé stahování obchodů je **křehké** — parser vrací null a zaloguje varování, když nenajde očekávaná data; nikdy neuloží nulu ani nesmysl jako cenu
- Šetrnost k obchodům: dávky, pauzy mezi požadavky, hlavičky běžného prohlížeče jen tam, kde bez nich obchod neodpoví (zjištěno v R7)

### Komentáře
- **Každá metoda má PHPDoc** s popisem, co dělá
- Komentáře tam, kde **WHY není zjevné** — žádné `// inkrementuj počítadlo`
- Doménové třídy mají třídní PHPDoc popisující účel; odkaz na rozhodnutí (`R9`) tam, kde z něj kód vychází

### Testy
- **Pest**, popis testu česky jako věta o chování: `it('odmítne špatné heslo')`
- **Feature testy** pokrývají use-case end-to-end (HTTP request / artisan → stav DB)
- **Unit testy** pro doménové třídy s netriviální logikou (parsery obchodů, převody měn)
- Testy běží proti MariaDB `games_test`, ne SQLite — stejně jako produkce
- **Žádný test nevolá skutečné API ani obchod** — `Http::preventStrayRequests()` v `TestCase`. Odpovědi zdrojů jsou **skutečné, zachycené odpovědi** uložené v `tests/Fixtures/<zdroj>/` (zkrácené, bez klíčů) — nové testy je používají, nevymýšlí vlastní tvar dat
- Účty v testech přes `user()` z `tests/Pest.php`, heslo `UserFactory::PASSWORD`
- Zachycené odpovědi načítá `jsonFixture('itad/…')` (nad `fixture()` z Pestu — vlastní funkce se tak jmenovat nesmí). Všechna API (ITAD, gg.deals, ČNB) podvrhne `fakeApis()`; výpadek zdroje `fakeApis(['api.gg.deals/*' => apiError(503)])`. Další `Http::fake()` v testu dřívější podvrhy **nepřebije** (vyhraje první shoda), proto `fakeApis()` vymění celý HTTP klient
- Účet se sledovanými hrami i s cenami z fixtures: `watcherOf([BIOMUTANT_ID, …])`

### Error handling
- Doménové výjimky jako vlastní třídy, ne holé `\Exception`
- **Žádný try/catch jen pro „polknutí" chyby** — výjimka u externího zdroje se zaloguje (`report()`/`Log::warning`) a krok cronu pokračuje dalším zdrojem

### Fronta a plánované úlohy
- Produkce běží na sdíleném hostingu **bez SSH, bez démona a bez workeru fronty** — nic, co čeká na `queue:work` nebo `schedule:run`, se tam nevykoná (R2)
- Nic nesmí implementovat `ShouldQueue`
- Periodické úlohy (stahování cen, upozornění, úklid cache) spouští cron hostingu voláním URL s tokenem; kroky nezávislé, souběh hlídá `Cache::lock`

---

## 4. Databáze

### Migrace
- **Každá změna schématu = nová migrace.** Nikdy se neupravuje migrace, která už běžela na produkci
- Každá migrace má funkční `down()` a PHPDoc u `up()`/`down()`
- Sloupce mají `comment()` — u částek měna nebo jednotka

### Migrace na produkci
Na hostingu **nejde spustit `php artisan migrate`**. Proto:

1. Každá nová migrace dostane ve **stejném commitu** SQL skript `deploy/migrations-<YYYY-MM-DD>-<popis>.sql`, který provede totéž (opravy dat `deploy/data-<YYYY-MM-DD>-<popis>.sql`) a zapíše migraci do tabulky `migrations`
2. Skript je **opakovatelný** (`IF NOT EXISTS`, `WHERE NOT EXISTS`) a pouští se v phpMyAdminu **před** nahráním nového kódu
3. Skript se ověří lokálně na prázdné databázi **dvakrát po sobě** a zapíše do tabulky v [deploy/DEPLOYMENT.md](../deploy/DEPLOYMENT.md)

### Datové typy a pojmenování
- Tabulky `snake_case` v plurálu, sloupce `snake_case`, primární klíč `id`
- Částky `decimal(10,2)` + `currency` `char(3)`
- **Časy v UTC** — aplikace, databáze i session spojení (`config/database.php`), do `Europe/Prague` až při zobrazení
- **Do prohlížeče vždy ISO 8601 s posunem** (`toIso8601String()`)

---

## 5. Frontend — Inertia + Vue 3

- Stránky v `resources/js/Pages`, rozvržení v `resources/js/Layouts`, sdílené komponenty v `resources/js/Components`, pomocné funkce v `resources/js/lib`
- **`<script setup>`**, props s typy a výchozí hodnotou
- **Žádné texty natvrdo** — vše přes `useTranslations()` z `resources/js/lib/i18n.js`, klíče ve skupině `app.ui` v `lang/cs/app.php`
- Čísla, ceny a časy přes `Intl` (`resources/js/lib/format.js`, přibude s cenami) — nikdy ruční skládání; cena vždy s měnou
- Data do stránky připravuje server (doménová třída) — komponenta nepočítá agregace ani nepřevádí měny
- Formuláře přes `useForm` z Inertie; chyby z validace serveru u pole (`aria-invalid`, `aria-describedby`)
- Grafy (etapa 7): ECharts přes `vue-echarts`, barvy z CSS tokenů
- Žádný jQuery

---

## 6. Stylování

### Závazná pravidla
- **Žádné inline styly** — žádné `style="…"` ani `:style` s pevnými hodnotami
- **Žádný `<style>` blok ve Vue komponentách** — styly patří do `resources/scss/`
- **Žádné utility třídy (Tailwind)** — BEM třídy (`.panel__title`, `.button--primary`)
- **Barvy, záře, mezery, písmo a délky animací jen z tokenů** v `base/_tokens.scss` (CSS custom properties)
- **Vzhled je jen tmavý** (R6) — žádný přepínač vzhledu ani světlé tokeny
- **Animace jen přes mixin `with-motion`** — při omezeném pohybu v systému se nehýbe nic
- Neonové efekty přes tokeny `--glow-*`, `--text-glow-*`, `--svg-glow-*` a mixin `neon-focus` — ne vlastní stíny v komponentě
- Kontrast textu: běžný text `--color-text`, vedlejší `--color-text-muted` — neon na ploše jen pro akcenty, nadpisy a rámy

### Struktura

```
resources/scss/
  app.scss              ← entry point, jen @use
  abstracts/            ← negeneruje CSS: _variables.scss (breakpointy), _mixins.scss
  base/                 ← _tokens.scss (design tokeny), _reset.scss (dokument, mřížka, řádkování)
  layout/               ← kostra stránky (_app-header.scss, _page.scss, _auth.scss)
  components/           ← jedna komponenta = jeden soubor (_panel.scss, _button.scss)
  pages/                ← rozvržení konkrétní stránky
```

- **`@use`, nikdy `@import`**
- Nikdy `id` selektory pro styling

### Písma
Fontsource balíčky (`@fontsource-variable/oxanium`, `@fontsource/chakra-petch`,
`@fontsource-variable/jetbrains-mono`) importuje `resources/js/app.js`; Vite je
přibalí do buildu. Nové písmo musí mít **latin-ext** (česká diakritika) a jít
z vlastní domény (CSP).

### Logo a ikony
Zdrojem je **`resources/images/logo.svg`** (stejná kresba jako komponenta
`BrandLogo.vue`, ta ale barví tokeny). Ostatní soubory se z něj generují
a verzují, ručně se neupravují:

| Soubor | Velikost | Použití |
|---|---|---|
| `public/favicon.svg` | vektor | moderní prohlížeče (kopie zdroje) |
| `public/favicon.ico` | 16 / 32 / 48 px | záložka prohlížeče |
| `public/apple-touch-icon.png` | 180 px | ikona na ploše iPhonu |

Po změně loga (nic se neinstaluje lokálně; vnitřní vykreslovač SVG
v ImageMagicku nekreslí obrysy, proto PNG dělá resvg):
```bash
cp resources/images/logo.svg public/favicon.svg
docker compose run --rm --no-deps app sh -lc 'cd /tmp && npm i --no-save --silent @resvg/resvg-js@2.6.2 && NODE_PATH=/tmp/node_modules node /var/www/html/docker/tools/render-icons.cjs'
docker run --rm -v "$PWD:/w" -w /w --entrypoint sh dpokidov/imagemagick -c '
  cp storage/icons/icon-180.png public/apple-touch-icon.png &&
  magick storage/icons/icon-16.png storage/icons/icon-32.png storage/icons/icon-48.png public/favicon.ico &&
  rm -r storage/icons'
```

---

## 7. Texty a lokalizace

- Všechny texty v `lang/cs/app.php` — PHP (`__('app.…')`) i Vue (skupina `app.ui`)
- Placeholdery `:name`, plurály přes `trans_choice()` a ve Vue tvary oddělené `|` (čeština má tři tvary)
- Hlášky validace mají vlastní texty v `lang/cs/app.php` (`messages()` ve Form Requestu) — aplikace nemá přeložené validační texty Laravelu
- Chybějící klíč se zobrazí jako holý text — to je **bug**
- Názvy her a obchodů se nepřekládají — jsou to data

---

## 8. Git workflow

- Pracuje se v **`main`**, který musí být vždy nasaditelný — commit až po projití kontrol (sekce 9)
- Předmět commitu je **česká věta „Oblast: co se změnilo"**, bez prefixů Conventional Commits
- **Tělo commitu popisuje co a proč**; při zásahu do více oblastí odrážky
- Na konci těla **dopad na nasazení**: nová migrace a SQL skript, nový build assetů, nové proměnné v `.env`
- Dokumentace (PLAN, guidelines, DEPLOYMENT, CLAUDE.md) se mění **ve stejném commitu** jako kód

```
Kostra: Laravel, přihlášení bez registrace a retrofuturistický vzhled
Katalog: přidání hry z IsThereAnyDeal podle názvu nebo Steam App ID
```

---

## 9. Nástroje a kvalita

Kontroly se pouštějí **ručně před každým commitem**. Commit, po kterém některá neprojde, do `main` nepatří.

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app npm run build          # při změně JS, Vue nebo SCSS
```

CI na GitHubu není (R17) — ruční kontroly jsou jediná pojistka, proto je pouštěj vždy.

Změnu vzhledu ověř v prohlížeči na desktopu i na šířce telefonu (390 px)
a s omezeným pohybem.

---

## 10. Bezpečnost

- **Vše kromě přihlášení za přihlášením** (middleware `auth`), cron URL chráněná tokenem z `.env` (špatný token = 404) a rate limitem (R3)
- **Přihlášení:** limit pokusů na e-mail + IP (`games.auth`), po přihlášení nová session, při odhlášení zneplatnění session i CSRF tokenu
- **Žádná registrace ani obnova hesla** — účty jen příkazem `games:create-user`; heslo nikdy jako parametr příkazu, nikdy v repu ani v logu (R4)
- Tajemství v `.env`, nikdy v repu — `.env.example` má prázdné hodnoty
- CSRF všude kromě cron URL (ta je jen GET a mimo `web`)
- **CSP bez cizích domén** pro skripty a písma; jedinou výjimkou jsou obrázky her z CDN ITAD (`img-src https://assets.isthereanydeal.com`) — nový zdroj obrázků se přidává jmenovitě
- **SSRF:** server volá jen adresy zdrojů z konfigurace a URL produktů s povoleným hostitelem (sekce 3)

---

## 11. Co nedělat

- Neukládejte časy v místní zóně — vždy UTC
- Neukládejte ceny jako `float` a nemíchejte měny
- Nepřidávejte Redis, frontu ani nic, co potřebuje démona — produkce to nemá (R2)
- Nepřidávejte headless prohlížeč ani služby na obcházení ochrany obchodů (R8)
- Nehledejte produkty obchodů s klíči podle názvu automaticky — jen URL zadaná uživatelem (R10)
- Nepoužívejte `dd()`, `dump()`, `console.log()` v commitech
- Neměňte produkční schéma v phpMyAdminu ručně — vždy migrace + SQL skript v `deploy/`
- Nenechávejte v `bootstrap/cache` cache rout ani configu — `php artisan optimize:clear`

---

*Autor: Roman Hlaváček · Vytvořeno: 2026-10-11*
