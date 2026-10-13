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
- **SMTP schránka** pro odesílání upozornění e-mailem (R13, R26) — ve WebAdminu → E-maily založ schránku (např. `game-watcher@rhsoft.cz`), údaje do `MAIL_*` v `.env`
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

Ve WebAdminu založ databázi **MariaDB 11.4** a v phpMyAdminu spusť **všechny**
skripty z tabulky *Historie SQL skriptů* **v pořadí shora dolů** (záložka
*Import*, nebo obsah vložit do *SQL*). Každý založí svoje tabulky a sloupce
a zapíše migrace do tabulky `migrations`; všechny jsou opakovatelné, takže
přerušené pořadí jde pustit znovu od začátku.

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
- `GAMES_IGDB_CLIENT_ID`, `GAMES_IGDB_CLIENT_SECRET` — aplikace na dev.twitch.tv pro IGDB, nadcházející hry (R46; návod v `.env.example`). Bez nich cron seznam nestahuje a záložka Nadcházející zůstane prázdná
- `GAMES_CRON_TOKEN` — token cron URL (viz *Cron: stahování cen*)
- `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` — schránka pro upozornění e-mailem (R26)
- `GAMES_PUSH_PUBLIC_KEY`, `GAMES_PUSH_PRIVATE_KEY` — `docker compose exec app php artisan games:push-keys`; `GAMES_PUSH_SUBJECT` = `mailto:` adresa schránky. Klíče se nemění — po změně je potřeba upozornění na zařízeních zapnout znovu

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

Kurzy ČNB, ceny sledovaných her, obnovu údajů her, žánry ze Steamu, Metascore z Metacritic, upozornění na ceny, týdenní souhrn a úklid cache obstarává URL volaná cronem
ve WebAdminu (R19) — hosting nemá scheduler ani artisan.

1. V `.env` na hostingu vyplň `GAMES_CRON_TOKEN` — náhodný řetězec:
   `docker compose exec app php -r "echo bin2hex(random_bytes(24));"`
2. Ve WebAdminu založ cron s voláním URL **každé 4 hodiny**:
   `https://game-watcher.rhsoft.cz/cron/ceny?token=<GAMES_CRON_TOKEN>`
3. Ověř v prohlížeči stejnou URL — odpověď je prostý text:
   ```
   kurzy ČNB — uložen lístek z 9. 10. 2026
   ceny — ITAD her: 4, gg.deals her: 3, PlayStation Store produktů: 1
   údaje her z ITAD — obnoveno: 3
   žánry a režimy ze Steamu — her: 2
   Metascore z Metacritic — her: 1
   upozornění — her: 1, e-mailů: 1, na zařízení: 1
   týdenní souhrn — e-mailů: 0
   cache — smazáno prošlých záznamů: 6
   ```
   Špatný nebo chybějící token vrací **404**.

Kroky na sobě nezávisí: když jeden selže (výpadek ČNB, ITAD nebo gg.deals,
neplatný klíč), ostatní proběhnou, místo čísla je „chyba“ a URL vrátí **500**
(podrobnosti v `storage/logs/laravel-*.log`). Když ještě běží předchozí volání,
vrátí **409** a nic neudělá; zámek po pádu PHP vyprší sám za 10 minut
(`games.cron.lock_seconds`). Ceny se stahují jen hrám, které někdo sleduje;
kurz ČNB jen tehdy, když uložený není dnešní (o víkendu platí páteční).
Upozornění, které nedoručil žádný kanál (chyba SMTP i push), se v souhrnu
ukáže jako „nedoručeno“, URL vrátí **500** a zkusí ho další běh (R26).

Výsledek každého zdroje ukazuje stránka **Nastavení → Stav zdrojů** (`/stav-zdroju`,
R29): kdy se který zdroj naposledy povedl, kolik toho stáhl a poslední chyba.
Výpadek zdroje ohlásí i přehled.

## Monitoring: hlídání cronu a zdrojů

URL **`https://game-watcher.rhsoft.cz/health/zdroje`** (veřejná, bez tokenu — prozrazuje
jen stáří) vrací **200**, když cron běží a hlídané zdroje se povedly, jinak **503** (R29).
Na každém řádku jeden zdroj, např. `PlayStation Store — VÝPADEK: poslední úspěch před 545 min`.

- **výpadek cronu** = poslední běh starší než 5 h (`games.monitoring.cron_max_age_minutes`) — hosting cron přestal volat
- **výpadek zdroje** = poslední úspěch starší než 9 h (`games.monitoring.stale_after_minutes`), tedy dva
  neúspěšné běhy po sobě; jeden neúspěšný běh je jen „CHYBA“ a 503 nevrací
- obnova údajů her, Steam (žánry a režimy) a Metacritic (Metascore her mimo ITAD) se nehlídají — jsou doplňkové (R25, R33, R34)
- změna formátu PS Store (stránka bez dat obchodu) je výpadek zdroje, dosavadní ceny zůstanou

Hned po nasazení, než cron poprvé proběhne, vrací 503 — zavolej cron URL ručně.

Nastavení v [UptimeRobot](https://uptimerobot.com) (zdarma, stejně jako u Počasí):
1. *Add New Monitor* → typ **HTTP(s)**
2. URL `https://game-watcher.rhsoft.cz/health/zdroje`, interval **5 minut**
3. Alert contact: e-mail (případně mobilní aplikace)

Monitoring nevolá cron URL — kontrola každých 5 minut by jinak pokaždé stahovala ceny.

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
| `migrations-2026-10-11-init.sql` | výchozí schéma (users, cache, cache_locks, migrations); opakovatelný, pustit **před** prvním nahráním kódu | 2026-10-12 |
| `migrations-2026-10-11-katalog.sql` | katalog her a seznam sledovaných (etapa 2): tabulky `games`, `watchlist_items` — prázdné; opakovatelný, pustit **po** init a **před** nahráním kódu | 2026-10-12 |
| `migrations-2026-10-11-ceny.sql` | ceny a měny (etapa 3): tabulky `current_prices`, `price_lows`, `price_history`, `exchange_rates`, sloupec `users.display_currency` — prázdné, naplní je cron; opakovatelný, pustit **po** katalogu a **před** nahráním kódu | 2026-10-12 |
| `migrations-2026-10-12-playstation.sql` | PlayStation Store (R20): tabulka `store_listings`, v `current_prices` sloupce `platform`, `edition`, `member_amount`, `member_included`; opakovatelný, pustit **po** cenách a **před** nahráním kódu | 2026-10-12 |
| `migrations-2026-10-12-watch-platform.sql` | hlídaná platforma (R24): ve `watchlist_items` sloupec `platform` (NULL = všude, i pro dosud sledované hry); opakovatelný, pustit **po** PlayStation a **před** nahráním kódu | 2026-10-12 |
| `migrations-2026-10-12-game-details.sql` | žánry a režimy (R25): v `games` sloupce `genres`, `modes`, `steam_details_at`, ve `store_listings` `local_players`, `online_players` — prázdné, doplní je cron; opakovatelný, pustit **po** hlídané platformě a **před** nahráním kódu | 2026-10-12 |
| `migrations-2026-10-12-price-alerts.sql` | hlídání cen (R26): ve `watchlist_items` cílová cena a poslední upozornění, v `users` `notify_email` (zapnuto) a `has_ps_plus`, tabulka `push_subscriptions`; opakovatelný, pustit **po** žánrech a **před** nahráním kódu | 2026-10-12 |
| `migrations-2026-10-12-source-statuses.sql` | stav zdrojů (R29): tabulka `source_statuses` — prázdná, naplní ji první běh cronu; opakovatelný, pustit **po** hlídání cen a **před** nahráním kódu | 2026-10-12 |
| `migrations-2026-10-12-ratings.sql` | hodnocení (R33): v `games` sloupce `metacritic_*`, ve `store_listings` `star_rating`, `star_ratings_count` — prázdné, doplní je cron a stažení cen; opakovatelný, pustit **po** stavu zdrojů a **před** nahráním kódu | 2026-10-12 |
| `migrations-2026-10-13-plus-and-digest.sql` | PS Plus a týdenní souhrn (R35, R36): v `current_prices` `member_tier`, `member_free` (doplní je stažení cen), v `users` `notify_weekly` (zapnuto) a `weekly_digest_at`; opakovatelný, pustit **po** hodnocení a **před** nahráním kódu | 2026-10-13 |
| `migrations-2026-10-13-release-alerts.sql` | nevydané hry (R41): ve `watchlist_items` `release_notified_at`, `first_sale_notified_at`; vydané hry označí jako upozorněné; opakovatelný, pustit **po** PS Plus a souhrnu a **před** nahráním kódu | 2026-10-13 |
| `migrations-2026-10-13-playtime.sql` | délka hraní (R44): v `games` sloupce `hltb_*` — prázdné, doplní je cron a přidání hry; opakovatelný, pustit **po** nevydaných hrách a **před** nahráním kódu | 2026-10-13 |
| `migrations-2026-10-13-upcoming-releases.sql` | nadcházející hry (R46): tabulka `upcoming_releases` — prázdná, naplní ji první běh cronu s klíčem IGDB v `.env`; opakovatelný, pustit **po** délce hraní a **před** nahráním kódu | 2026-10-13 |
| `migrations-2026-10-13-czech.sql` | čeština ve hře (R47): v `games` a `store_listings` sloupec `czech`; při prvním běhu vynuluje `games.steam_details_at`, aby cron po dávkách stáhl údaje ze Steamu znovu i s jazyky (PS Store doplní obnova cen); opakovatelný, pustit **po** nadcházejících hrách a **před** nahráním kódu | |

## Nasazené verze

Co běží na produkci — pro `git log <commit>..HEAD` při dalším nasazení
(nové SQL skripty, změněné složky, nové proměnné v `.env`).

| Datum | Commit | Co |
|---|---|---|
| 2026-10-12 | `28d1a3b` | první nasazení — etapy 1–3 (přihlášení, katalog z ITAD, ceny ITAD a gg.deals, kurzy ČNB, PlayStation Store, filtry přehledu, hlídaná platforma, žánry a hráči ze Steamu); všechny SQL skripty spojené v jednom importu, cron každé 4 hodiny |
| 2026-10-12 | `b261ed2` | etapa 6 — hlídání cen: cílová cena, upozornění e-mailem (SMTP schránka Websupportu) a na zařízení (Web Push, klíče VAPID), nastavení účtu, slevy dne, aplikace na plochu; SQL `migrations-2026-10-12-price-alerts.sql`, nové `MAIL_*` a `GAMES_PUSH_*` v `.env`, nahrán i `vendor/` (minishlink/web-push) |
| 2026-10-12 | `3948076` | etapa 7 — graf vývoje ceny na detailu hry (historie ITAD, vlastní historie klíčů a PS Store, minimum klíčů z gg.deals); jen nový build, bez SQL a změn `.env` |
| 2026-10-12 | `accce34` | stav zdrojů a monitoring `/health/zdroje` (R29), zdroj ceny a filtry hledání (R30), Tron vzhled (R31), výchozí řazení od nejlevnější a Metascore na kartě (R32), Metascore z Metacritic pro PS hry a hvězdičky z PS Store (R33); SQL `migrations-2026-10-12-source-statuses.sql` a `migrations-2026-10-12-ratings.sql`, bez změn `.env` |
| 2026-10-13 | `e6149f7` | obnova údajů her z ITAD (R34), úroveň katalogu a měsíční hry PS Plus s upozorněním (R35), týdenní souhrn e-mailem (R36), aplikace na ploše — zkratky, maskovatelná ikona, sdílení odkazu, číslo na ikoně, stránka offline, stažení dolů, iPhone (R37); SQL `migrations-2026-10-13-plus-and-digest.sql`, nový `public/icon-maskable-512.png`, bez změn `.env` |
| 2026-10-13 | `db8f8ef` | tlačítko Zpět na stejné místo přehledu (R38), banner hry na telefonu (R39), hlavička neplave a Zpět problikává (R40), upozornění na vydání a první slevu s filtrem nevydaných her (R41), „Kup, nebo počkej?“ na detailu (R42), stránka Objevit (R43), délka hraní z HowLongToBeat a cena za hodinu (R44, ověřeno z hostingu); SQL `migrations-2026-10-13-release-alerts.sql` a `migrations-2026-10-13-playtime.sql`, bez změn `.env` a `vendor/` |
| 2026-10-13 | `b9f0f7e` | návrat nahoru a filtry na telefonu v okně (R45), nadcházející hry z IGDB na záložce Objevit se sledováním jedním klikem (R46, etapa 8); SQL `migrations-2026-10-13-upcoming-releases.sql`, v `.env` nově `GAMES_IGDB_CLIENT_ID` a `GAMES_IGDB_CLIENT_SECRET`, změněný `public/.htaccess` (CSP), bez změn `vendor/` |
