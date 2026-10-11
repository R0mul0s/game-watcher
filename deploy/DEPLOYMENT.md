<!--
  Game Watcher — nasazení na webhosting
  @author Roman Hlaváček
  @created 2026-10-11
-->

# Nasazení na webhosting

Postup pro shared hosting **Websupport** a subdoménu `game-watcher.rhsoft.cz`
(R12) — stejný hosting a postup jako u projektu Počasí.

> **Na hostingu nejdou spouštět příkazy** — není tam SSH ani composer. Nic
> z `php artisan …` se na produkci nepouští: schéma se zakládá SQL skriptem
> v phpMyAdminu, `vendor/` a assety se sestaví lokálně, účty se zakládají SQL
> skriptem z `games:create-user --sql` (R2, R4).

## Obsah `deploy/`
| Soubor | K čemu |
|---|---|
| `build-upload.ps1` | sestaví nahrávací balíček do `deploy/upload/` |
| `.env.production.example` | šablona `.env` pro hosting |
| `migrations-*.sql` | změny schématu pro produkci (verzované) |
| `data-*.sql` | opravy a doplnění dat pro produkci (verzované) |
| `root-htaccess-fallback` | nouzové řešení, když nejde nasměrovat document root |

## Předpoklady na hostingu
- **PHP 8.4** s `pdo_mysql`, `mbstring`, `intl`, `curl`, `openssl`
- **MariaDB 11.4**
- **Odchozí HTTPS** — API IsThereAnyDeal a gg.deals, obchody (ověřeno diagnostikou 11. 10. 2026, R7)
- **Subdoména `game-watcher.rhsoft.cz`** založená ve WebAdminu, **document root** nasměrovaný do `public/`
  (WebAdmin → Web → Služby → Upravit). Přesměrování na HTTPS ve WebAdminu může zůstat
  zapnuté — na rozdíl od Počasí sem nic neposílá HTTP zařízení; `public/.htaccess` přesměrovává i tak
- *(etapa 6)* **SMTP schránka** pro odesílání upozornění e-mailem (R13) — `MAIL_*` v `.env`
- `mod_rewrite` a `mod_headers` (HTTPS přesměrování a bezpečnostní hlavičky v `public/.htaccess`)

---

## 1) Sestav balíček

V rootu repa, s běžícím `docker compose`:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build-upload.ps1
```

Vznikne **`deploy/upload/`** — jen to, co aplikace potřebuje za běhu (`app`,
`bootstrap`, `config`, `database`, `lang`, `public` se sestavenými assety,
`resources/views`, `routes`, `vendor` bez dev balíčků). Ze `storage/` jde jen
prázdná kostra adresářů.

Skript balí jen **commitnutý** stav — s necommitnutými změnami skončí chybou
(pro zkoušku přepínač `-AllowDirty`). Hash commitu zapíše do `public/version.txt`.

## 2) Připrav databázi (jen první nasazení)

Ve WebAdminu založ databázi **MariaDB 11.4** a v phpMyAdminu spusť
`deploy/migrations-2026-10-11-init.sql`. Založí všechny tabulky a zapíše
migrace do tabulky `migrations`. Skript je opakovatelný.

## 3) Nahraj soubory

Obsah `deploy/upload/` nahraj přes FTP do kořene aplikace. Document root má
mířit do `.../public`.

> **Nejde změnit docroot?** Nahraj do kořene `deploy/root-htaccess-fallback`
> přejmenovaný na `.htaccess` — přesměruje vše do `public/` a chrání `.env`,
> `vendor`, `storage` atd. i bez `mod_rewrite`.

## 4) Vytvoř `.env`

Zkopíruj `deploy/.env.production.example` na hosting jako **`.env`** (bez BOM)
a vyplň místa `<…>`:

- **`APP_KEY`** — `docker compose exec app php artisan key:generate --show`, vlož i s prefixem `base64:`
- **`APP_URL`** — produkční adresa s `https://`
- `DB_*` — z detailu databáze ve WebAdminu
- `GAMES_ITAD_API_KEY`, `GAMES_GGDEALS_API_KEY` — klíče zdrojů cen (R9); pro produkci ideálně vlastní aplikace/klíč, ne vývojový

> **Ladění bez SSH:** chyby jsou v `storage/logs/laravel-RRRR-MM-DD.log` —
> stáhni ho přes FTP. `APP_DEBUG=true` na produkci nezapínej ani dočasně:
> stack trace s SQL, cestami a hlavičkami by po tu dobu viděl kdokoli.

## 5) Založ první účet

Lokálně vygeneruj SQL s hashem hesla (heslo se zadává skrytě, do SQL jde jen hash):

```bash
docker compose exec app php artisan games:create-user roman@example.com --name=Roman --sql
```

Výstup (`INSERT INTO users …`) spusť v phpMyAdminu. Účet se lokálně neuloží.
Stejně se mění zapomenuté heslo: nový hash a `UPDATE users SET password = '…' WHERE email = '…'`
(hash vypíše tentýž příkaz s `--sql`). SQL s hashem se neukládá do repa.

## 6) Ověř

```bash
curl -I "http://game-watcher.rhsoft.cz/prihlaseni"
curl -I "https://game-watcher.rhsoft.cz/"
curl -I "https://game-watcher.rhsoft.cz/prihlaseni"
```
1. HTTP vrací **301** na `https://…`.
2. Nepřihlášený na `/` dostane **302** na `/prihlaseni`.
3. HTTPS odpověď obsahuje `Strict-Transport-Security`, `Content-Security-Policy`
   a `X-Robots-Tag: noindex, nofollow`.
4. Přihlášení vytvořeným účtem projde; asset z `/build/assets/` má `Cache-Control: … immutable`.
   Proxy hostingu statické soubory cachuje i s hlavičkami — ověřuj s unikátním `?v=…`.

## Cron: stahování cen

*(etapa 3)* URL `/cron/refresh-prices?token=<GAMES_CRON_TOKEN>` volaná cronem
ve WebAdminu — postup se doplní s implementací.

---

## Aktualizace už nasazené verze

1. **Záloha databáze**, pokud jdou na produkci SQL skripty — viz *Záloha databáze*
2. Spusť nové skripty `deploy/migrations-*.sql` z tabulky níž (v pořadí, **před** nahráním kódu)
3. `build-upload.ps1` a nahraj obsah `deploy/upload/` přes FTP
   (`.env` na hostingu nepřepisuj — v balíčku není). Když se nezměnil `composer.lock`,
   `vendor/` nahrávat nemusíš — **`bootstrap/cache/packages.php` ale ano**: Laravel ho
   na hostingu přegeneruje jen tehdy, když chybí
4. Soubory, které z repa zmizely, FTP samo nesmaže — smaž je ručně
   (`git diff --name-status --diff-filter=D <nasazený commit> HEAD`)
5. Nové proměnné v `deploy/.env.production.example` doplň do `.env` na hostingu
6. Ověř verzi: `https://game-watcher.rhsoft.cz/version.txt?v=<cokoli>` musí vrátit nasazený commit
   a zapiš ji do tabulky *Nasazené verze*

## Záloha databáze

Před každým SQL skriptem, který mění data nebo schéma, a jinak aspoň jednou měsíčně:

1. phpMyAdmin → databáze → *Exportovat* → metoda *Vlastní*, formát SQL,
   komprese **gzip**, zaškrtnout *Přidat příkaz DROP TABLE*
2. Tabulky: všechny kromě `cache` a `cache_locks`
3. Soubor ulož mimo hosting jako `games-RRRR-MM-DD.sql.gz`

Historie cen se zpětně stáhnout nedá (zdroje dávají jen aktuální cenu a minimum) — záloha
je jediná cesta, jak ji neztratit.

## Historie SQL skriptů

| Skript | Obsah | Nasazeno |
|---|---|---|
| `migrations-2026-10-11-init.sql` | výchozí schéma (users, cache, cache_locks, migrations); opakovatelný, pustit **před** prvním nahráním kódu | |
| `migrations-2026-10-11-katalog.sql` | katalog her a seznam sledovaných (etapa 2): tabulky `games`, `watchlist_items` — prázdné; opakovatelný, pustit **po** init a **před** nahráním kódu | |

## Nasazené verze

Co běží na produkci — pro `git log <commit>..HEAD` při dalším nasazení
(nové SQL skripty, změněné složky, nové proměnné v `.env`).

| Datum | Commit | Co |
|---|---|---|
| | | zatím nenasazeno |
