<!--
  Game Watcher — plán projektu
  @author Roman Hlaváček
  @created 2026-10-11
-->

# Game Watcher — plán projektu

Soukromá aplikace pro **hlídání cen her** napříč obchody — oficiálními (Steam,
PlayStation Store, Epic, Ubisoft, Battle.net) i obchody s klíči (Kinguin, G2A,
Loaded, Eneba). Cílem je na jednom místě vidět, kde je hra právě nejlevnější,
jak se její cena vyvíjí a dostat upozornění, když spadne pod zvolenou mez.

- **Produkce:** `game-watcher.rhsoft.cz` (shared hosting Websupport, R12) — zatím nenasazeno
- **Vývoj:** `http://localhost:54730` (Docker, viz [compose.yaml](../compose.yaml))
- **Pravidla pro psaní kódu:** [CODING_GUIDELINES.md](CODING_GUIDELINES.md)
- **Odložené úkoly a nápady:** [TODO.md](TODO.md)
- **Vzor projektu:** Počasí (`C:\Github\Počasí`) — stejný stack, hosting a způsob práce (R1)

---

## 1. Rozsah a cíl

### Co systém dělá (cíl)
- Jen pro přihlášené — účty zakládá správce, registrace není (R3, R4)
- Vede **katalog her** převzatý z IsThereAnyDeal — název, obrázky, Steam App ID, typ (hra / DLC) (R10)
- Každý uživatel má **vlastní seznam sledovaných her** s cílovou cenou; katalog a ceny jsou společné (R11)
- Pravidelně (cron URL) stahuje ceny: oficiální obchody přes IsThereAnyDeal, obchody s klíči souhrnně přes gg.deals, PlayStation Store, Kinguin a Eneba přímo (R9)
- Ukládá **historii cen** a ukazuje historická minima
- Ukazuje ceny **v CZK** (denní kurz ČNB) s přepnutím na **EUR** (R14)
- Upozorní **e-mailem a přes Web Push**, když cena sledované hry spadne pod cílovou (R13)

### Co už umí (etapy 1–3, PlayStation z etapy 5)
- Přihlášení e-mailem a heslem s ochranou proti hádání hesla, odhlášení, „zůstat přihlášen“ (R4)
- Zakládání účtů příkazem `games:create-user`, pro produkci jako SQL (`--sql`)
- Retrofuturistický vzhled (R6)
- **Přidání hry** — hledání v katalogu IsThereAnyDeal podle názvu, Steam App ID nebo odkazu na Steam (R15)
- **Ceny** (R18): nejlevnější oficiální obchod a nejnižší cena obchodů s klíči na kartách přehledu; na detailu všechny obchody od nejlevnějšího se slevou, běžnou cenou, aktivací (DRM), koncem slevy a odkazem do obchodu, historická minima
- **CZK / EUR** přepínačem v hlavičce, převod kurzem ČNB (R14)
- **Cron URL** `/cron/ceny` — kurz ČNB, ceny sledovaných her, úklid cache (R19)
- **PlayStation Store** (R20): produkt se ke hře připojí odkazem na detailu; ve výpisu cen je jako samostatná položka se štítkem platformy (PS4/PS5) a edicí, s běžnou cenou, slevou, cenou s PS Plus a příznakem katalogu PS Plus Extra
- **Graf vývoje ceny** (R28): na detailu hry denní nejnižší cena — nejlevnější obchod pro PC a Steam zpětně až 2 roky z historie ITAD, obchody s klíči a PS Store z vlastní historie; minimum klíčů z gg.deals jako čára; období 3 měsíce / rok / vše, čára cílové ceny
- **Upozornění na ceny** (R26): cílová cena u sledované hry; bez ní upozornění na historické minimum ve slevě. E-mail a upozornění na zařízení (Web Push, i na iPhonu z plochy — R27), nastavení u účtu včetně PS Plus. Na přehledu „Zlevnilo od včerejška“
- **Hlídaná platforma** (R24): sledovanou hru jde hlídat všude, jen na PC nebo jen na PlayStationu — karta, nejnižší cena a filtry pak počítají jen s cenami té platformy; hru jde najít i podle odkazu z PS Store nebo playstation.com a produkt se při přidání rovnou připojí
- **Obchody ve výsledcích hledání** (R23): u každé nalezené hry štítky obchodů (Steam, Epic, GOG… napřed), PlayStation u hry s připojeným PS Store a nejnižší cena v měně účtu
- **Exkluzivity PlayStationu** (R22): hledání najde i hry, které má ITAD jen jako zástupný záznam (Saros, Gran Turismo 7); odkaz na PS Store nebo na stránku hry na playstation.com jim doplní cenu, obal, banner, datum vydání a vydavatele
- **Pokročilé filtry přehledu** (R21, R25): hledání, platforma, hráči (1 / 2+ / couch co-op), žánr, druh, cena do, sleva aspoň, jen ve slevě, „mám PS Plus“ a řazení — stav v adrese
- **Přehled** sledovaných her (karty s bannerem a datem vydání) a **detail hry** — obal, vydání, vývojář, vydavatel, hodnocení (Steam, Metacritic, OpenCritic), štítky, odkazy na Steam a ITAD (R16)

### Co systém nedělá
- Nenakupuje a neprovádí nic v obchodech — jen čte veřejné ceny
- Nepoužívá prohlížeč ani placené služby na obcházení ochrany obchodů (R8)
- Neslouží veřejnosti — žádná registrace, nic se neindexuje (R3)

---

## 2. Zdroje cen

Průzkum proveditelnosti proběhl 11. 10. 2026 (R7) na hrách CONTROL Resonant,
The Blood of Dawnwalker a BIOMUTANT — lokálně (Node, PHP v Dockeru, Chrome)
a na sdíleném hostingu diagnostickým skriptem
[research/check-stores.php](research/check-stores.php).

| Obchod | Z hostingu přímo | Jak se k ceně dostaneme | Poznámka |
|---|---|---|---|
| Steam | ✅ | **IsThereAnyDeal** (přímo i `store.steampowered.com/api/appdetails`) | CZ ceny v EUR |
| PlayStation Store | ✅ | **přímo** — HTML stránky produktu/konceptu (`discountedValue`, JSON-LD `offers`) | ceny v CZK; ITAD PlayStation nepokrývá; `curl` z Windows blokuje Akamai, PHP cURL a Node projdou |
| Epic Games Store | ❌ Cloudflare | **IsThereAnyDeal** | z domácí IP PHP projde, z datacentra hostingu ne |
| Ubisoft Store | ✅ (Algolia) | **IsThereAnyDeal** | prodává jen hry Ubisoftu |
| Battle.net | ✅ (s cookies) | **IsThereAnyDeal** (obchod „Blizzard“) | prodává jen hry Blizzardu a Activisionu |
| Kinguin | ✅ | **přímo** — interní JSON API `services/library/api/v1/products/search` | ceny v EUR centech, `1000000` = bez nabídky |
| Eneba | ✅ | **přímo** — data z Algolie vložená v HTML vyhledávání (`lowestPrice`) | sada měn podle země IP |
| G2A | ❌ Akamai | **gg.deals** — jen souhrnná nejnižší cena obchodů s klíči | z hostingu projde jen občas (1 ze 4 běhů) |
| Loaded (dřív CDKeys) | ❌ Cloudflare | **gg.deals** — souhrnně | Algolia API je volné, ale klíč (~24 h) jde získat jen ze stránky za Cloudflare |

**IsThereAnyDeal API** (klíč zdarma, limit 100 požadavků za 5 minut, až 200 her v jednom):
pro CZ pokrývá 35 obchodů včetně Steamu, Epicu, Ubisoftu, Blizzardu, GOG, GMG,
Fanaticalu a Humble. Ceny vrací po obchodech (cena, běžná cena, sleva, DRM,
odkaz) a historická minima. Hru identifikuje vlastním ID (UUID); převod ze Steam
App ID i z názvu umí.

**gg.deals API** (klíč zdarma pro nekomerční použití, **povinný odkaz na gg.deals**
u zobrazených dat): podle Steam App ID vrací jen souhrn — nejnižší aktuální
a historickou cenu oficiálních obchodů a obchodů s klíči. Cenu konkrétního
obchodu (G2A, Loaded) nedává. Region `cz` nezná, `eu` vrací EUR.

---

## 3. Technologie

| Vrstva | Volba | Poznámka |
|---|---|---|
| Jazyk | PHP 8.4 | Shodné s hostingem. |
| Framework | Laravel 13 | |
| Frontend | Inertia 3 + Vue 3 | Jako Počasí (R1). |
| Styly | SCSS, BEM, design tokeny v CSS proměnných | Bez Tailwindu; retrofuturistický vzhled jen v tokenech (R6). |
| Písma | Oxanium, Chakra Petch, JetBrains Mono (Fontsource) | Z vlastní domény přes Vite, latin-ext kvůli češtině (R6). |
| Databáze | MariaDB 11.4 | Jako Počasí. |
| Cache / session / queue | `database` / `cookie` / `sync` | Hosting nemá Redis ani worker fronty (R2, R5). |
| Testy | Pest 4, Larastan level 8, Pint | Testy proti MariaDB `games_test`. |

---

## 4. Datový model

### Dnes (etapy 1–3)

| Tabulka | Obsah |
|---|---|
| `users` | účty — jméno, e-mail, hash hesla, `remember_token`; bez ověřování e-mailu a obnovy hesla (R4) |
| `cache`, `cache_locks` | databázová cache a zámky (R2), výsledky hledání v ITAD (R15) |
| `games` | katalog z ITAD (R10): `itad_id` (UUID, unikátní — identita), `slug` (unikátní, adresa detailu), `title`, `type` (game/dlc…), `is_mature`, `steam_app_id` (prázdné mimo Steam), `release_date`, `developers` / `publishers` / `tags` / `reviews` (JSON), `boxart_url`, `banner_url`, `itad_url`, `info_refreshed_at`; ze Steamu `genres` (české názvy) a `modes` (enum `GameMode`) a `steam_details_at` (R25) |
| `watchlist_items` | osobní seznam (R11): `user_id` + `game_id` unikátně, mazání kaskádou s účtem i hrou; `platform` = kde se hra hlídá (`pc`, `playstation`, NULL = všude, R24) |
| `current_prices` | poslední cena hry v obchodě (R18): `game_id` + `shop_key` unikátně (`itad-<id obchodu>`, `keyshops` = nejnižší cena obchodů s klíči z gg.deals), `source` (itad/ggdeals), `shop_name`, `amount`, `regular_amount`, `currency`, `cut`, `drm`, `url`, `expires_at`, `fetched_at` |
| `price_lows` | historická minima ze zdrojů: klíč `game_id` + `scope` (`official` z ITAD, `keyshops` z gg.deals), `amount`, `currency` |
| `price_history` | nejnižší cena dne po obchodech (klíč `game_id` + `shop_key` + `day`, místní den) — podklad grafu (etapa 7) |
| `exchange_rates` | denní kurzy ČNB (R14): klíč `day` + `currency`, `rate` = CZK za 1 jednotku |
| `users.display_currency` | měna zobrazení účtu — `CZK` (výchozí) / `EUR` |
| `store_listings` | produkt obchodu bez ITAD připojený ke hře odkazem (R20): `game_id` + `source` + `external_id` unikátně, `name` (edice), `platforms`, `local_players` / `online_players` (počet hráčů z PS Store, R25), `url`; cena v `current_prices` pod klíčem `psn-<id propojení>` |
| `current_prices` (od R20) | navíc `platform` (pc / playstation), `edition`, `member_amount` (cena s PS Plus), `member_included` (v katalogu PS Plus Extra) |

Tabulky `sessions`, `password_reset_tokens` ani tabulky fronty nejsou (R2, R4, R5).

### Návrh pro další etapy (upřesní se při implementaci)

Hra je jedna věc napříč obchody — **identitu jí dává IsThereAnyDeal** (R10), ostatní
zdroje se k ní připojují:

```
users ──< watchlist_items >── games ──< store_listings      (ruční propojení: PSN, Kinguin, Eneba)
                                │
                                ├──< current_prices        (poslední cena v každém obchodě)
                                └──< price_history          (denní minimum po obchodech)
```

| Tabulka | Klíčové sloupce | K čemu |
|---|---|---|
| `store_listings` *(Kinguin, Eneba)* | `source` = kinguin / eneba, `name` („Steam klíč EU“) | konkrétní nabídka obchodu s klíči, připojená odkazem jako PS Store (R10, R20) |

**Měny (R14):** ITAD (CZ) a gg.deals (eu) vrací EUR, PlayStation Store CZK, Kinguin
a Eneba EUR (Eneba i CZK). Částky se ukládají **v měně zdroje** — porovnání napříč
měnami jen přes převod, nikdy přímo. Převádí se až při zobrazení posledním
lístkem ČNB (`exchange_rates`, `ExchangeRates`).

**Upozornění (R13, R26):** `watchlist_items` má cílovou cenu (`target_amount` + `target_currency`, NULL = historické minimum)
a poslední upozorněnou cenu (`notified_amount` + `notified_currency`, `notified_at`); `users` volbu e-mailu
(`notify_email`) a PS Plus (`has_ps_plus`); `push_subscriptions` zařízení s Web Push podle uživatele
(vzor z Počasí: adresa, hash adresy unikátně, klíče prohlížeče).

---

## 5. Toky dat

```
uživatel ──hledá (název / Steam App ID / URL Steamu)──▶ ITAD search nebo lookup (cache 1 h) ──▶ výsledky
uživatel ──Sledovat──▶ ITAD info (jen hra mimo katalog) ──▶ games + watchlist_items   (R15, R16)
uživatel ──vloží odkaz na PS Store (produkt / koncept)──▶ ověření hostitele a tvaru ──▶ stránka produktu (cs-cz) ──▶ store_listings + current_prices   (R20)
přehled ──filtry v adrese──▶ WatchlistView ──▶ PriceBoard (nejlevněji podle filtru, PS Plus) ──▶ karty   (R21)

cron hostingu (každé 4 h) ──▶ /cron/ceny?token=…                                   (R19)
      ├─ kurz ČNB (jen když uložený není dnešní, pokus nejvýš 1× za hodinu) ──▶ exchange_rates
      ├─ ITAD games/prices (CZ, dávka 200 her) ─────────┐
      ├─ gg.deals (eu, dávka 100 Steam App ID) ────────┼──▶ current_prices + price_lows + price_history   (R18)
      ├─ PlayStation Store (stránka produktu po jednom, pauza 0,5 s) ┘   (R20; Kinguin, Eneba později)
      ├─ úklid prošlé cache
      ├─ žánry a režimy ze Steamu (nejvýš 20 her)                               (R25)
      └─ upozornění: cíl / historické minimum ──▶ e-mail (SMTP Websupportu) + Web Push   (R13, R26)

uživatel ──Sledovat novou hru──▶ ceny hned (ITAD + gg.deals jen pro tu hru)          (R18)
stránka ──▶ PriceBoard ──▶ převod do měny účtu kurzem ČNB, řazení od nejlevnější    (R14)
detail hry ──odložený prop──▶ PriceChart ──▶ ITAD games/history/v2 (cache 6 h) + price_history ──▶ graf   (R28)
```

Jako v Počasí: hosting umí jen zavolat URL, žádný scheduler ani fronta (R2).
Kroky cronu na sobě nezávisí a souběh hlídá zámek — výpadek jednoho zdroje
nesmí zastavit ostatní.

---

## 6. Etapy

| # | Obsah | Stav |
|---|---|---|
| 1 | **Kostra** — Laravel 13 + Inertia + Vue v Dockeru, dokumentace, přihlášení bez registrace (R4), zakládání účtů příkazem, retrofuturistický vzhled (R6), CI (zrušené, R17), nasazovací skript a SQL skript výchozího schématu | hotovo 2026-10-11, nasazeno 2026-10-12 |
| 2 | **Katalog her z ITAD** — přidání hry podle názvu, Steam App ID nebo URL Steamu; detail hry (obal, vydání, vývojář, hodnocení, štítky, odkazy); osobní seznam sledovaných her na přehledu (R10, R11, R15, R16) | hotovo 2026-10-11, nasazeno 2026-10-12 |
| 3 | **Ceny z API a měny** — ITAD (oficiální obchody, CZ) a gg.deals (obchody s klíči, eu); cron URL s tokenem, zámek a nezávislé kroky; `current_prices`, `price_lows` a `price_history`; kurz ČNB a zobrazení v CZK / EUR s přepínačem (R14, R18, R19); přehled s nejnižší cenou, detail se všemi obchody a historickými minimy | hotovo 2026-10-11, nasazeno 2026-10-12 |
| 4 | **První nasazení** na `game-watcher.rhsoft.cz` (R12) — subdoména, databáze, SQL skripty, první účet, cron | hotovo 2026-10-12 (`28d1a3b`) |
| 5 | **Přímé zdroje** — PlayStation Store, Kinguin, Eneba: připojení produktu ke hře přes URL, stahování v cronu, testy nad zachycenými odpověďmi, hlídání změny formátu. Spolu s PS Store pokročilé filtry přehledu (R21) | PlayStation Store a filtry hotovo a nasazeno 2026-10-12 (R20–R25); Kinguin a Eneba zbývá |
| 6 | **Hlídání cen** — cílová cena, upozornění e-mailem a Web Push (R13), „slevy dne“ ve sledovaných | hotovo a nasazeno 2026-10-12 (R26, R27, `b261ed2`) |
| 7 | **Historie cen** — graf vývoje ceny (ECharts) | hotovo 2026-10-12 (R28), nenasazeno |

---

## 7. Otevřené otázky

| # | Otázka | Stav |
|---|---|---|
| O1 | **Doména a hosting** — stejný Websupport jako Počasí? Subdoména? | vyřešeno (R12): `game-watcher.rhsoft.cz` na Websupportu |
| O2 | **Kanál upozornění** — e-mail (SMTP hostingu), Web Push jako v Počasí, nebo Telegram/Discord bot? | vyřešeno (R13): e-mail i Web Push |
| O3 | **Měna zobrazení** — ceny v měně obchodu, nebo vše přepočítat na CZK? | vyřešeno (R14): CZK kurzem ČNB, přepínač na EUR |
| O4 | **Stačí pro G2A a Loaded souhrnná cena z gg.deals?** Konkrétní cenu z hostingu nezískáme (R7, R8) | |
| O5 | **Které edice a regiony u obchodů s klíči?** Navrženo: jen klíče platné v ČR/EU pro PC (Steam), bez účtů, darů a „Mystery“ klíčů — gg.deals podle všeho filtruje podobně | |
| O6 | **Jak často stahovat ceny** — limit ITAD 100 požadavků / 5 min s dávkou 200 her nevadí, přímé zdroje stahovat šetrně (po jednom listingu, s pauzou) | rozhodnuto (R19): cron každé 4 hodiny; přímé zdroje (etapa 5) se upřesní |

---

## 8. Log rozhodnutí

| # | Datum | Rozhodnutí | Proč |
|---|---|---|---|
| R1 | 2026-10-11 | **Stejný stack a způsob práce jako projekt Počasí** — Laravel 13, Inertia 3 + Vue 3, SCSS s BEM a tokeny, MariaDB 11.4, Pest + Larastan + Pint, vývoj výhradně v Dockeru, dokumentace v `docs/` (PLAN, CODING_GUIDELINES, TODO), `CLAUDE.md` + `AGENTS.md`, nasazení balíčkem přes FTP | Osvědčené na stejném typu hostingu; jeden způsob práce napříč osobními projekty. Porty 54730–54732, aby šly spustit vedle Počasí (5471x) a Letáků (5472x). |
| R2 | 2026-10-11 | **Sdílený PHP hosting bez SSH** — schéma SQL skriptem v phpMyAdminu, periodické úlohy přes cron URL s tokenem, žádná fronta, scheduler ani Redis; cache a zámky v databázi | Požadavek zadání („mini aplikace na PHP sharedhostingu“); stejná omezení jako u Počasí. |
| R3 | 2026-10-11 | **Soukromá aplikace** — vše kromě přihlášení za přihlášením, `noindex` v hlavičce stránek i v `X-Robots-Tag`, `robots.txt` zakazuje vše | Zadání: „žádná free část“. Seznam her a cen nikoho jiného nezajímá a API klíče se nemají zneužívat cizími požadavky. |
| R4 | 2026-10-11 | **Přihlášení vestavěným auth Laravelu bez registrace a bez obnovy hesla e-mailem.** Účty zakládá správce příkazem `games:create-user` (heslo skrytě, min. 12 znaků); pro produkci `--sql` vypíše `INSERT` s bcrypt hashem pro phpMyAdmin. Po 5 neúspěšných pokusech pro kombinaci e-mail + IP je přihlášení minutu zablokované (`games.auth`). „Zůstat přihlášen“ přes `remember_token` | Zadání: „v první řadě bez registrace“. Hosting nemá artisan — SQL s hashem je nejkratší bezpečná cesta, heslo se nikam neukládá v čitelné podobě. E-mail z hostingu by potřeboval SMTP — obnova hesla jde stejně udělat novým SQL. Limit podle vzoru startovacích sad Laravelu. |
| R5 | 2026-10-11 | **Session v šifrované cookie** (`SESSION_DRIVER=cookie`), tabulka `sessions` vůbec nevzniká | Zkušenost z Počasí (R36 tam): databázová session zapisuje při každém požadavku. Cenou je, že session nejde zrušit smazáním řádku — jen změnou `APP_KEY` nebo vypršením; u pár účtů přijatelné. |
| R6 | 2026-10-11 | **Vzhled retrofuturismus (Tron / cyberpunk), jen tmavý**: neonová azurová (akcent) a purpurová (doplněk) na téměř černém pozadí, jemná mřížka a řádkování obrazovky, panely s rohovými lištami HUD, přihlášení s obzorem a ubíhající perspektivní podlahou. Písma **Oxanium** (nadpisy), **Chakra Petch** (text), **JetBrains Mono** (čísla, pole) přes Fontsource z vlastní domény. Animace jen bez `prefers-reduced-motion`. Vše v tokenech `_tokens.scss` | Zadání: „hezký retrofuturismus, Tron / cyberpunk“. Tmavý vzhled je podstatou stylu — světlá varianta by ho popírala a zdvojila práci na tokenech. Písma musí mít latin-ext (Orbitron ani Share Tech Mono ho nemají — „ř“ by vypadlo z písma); z vlastní domény kvůli CSP a soukromí. |
| R7 | 2026-10-11 | **Průzkum proveditelnosti** (tabulka v sekci 2): z hostingu jde přímo Steam, PlayStation Store, Kinguin, Eneba, Ubisoft a Battle.net; **Epic, G2A a Loaded ne** (Cloudflare / Akamai na datacentrové IP) | Ověřeno skriptem [research/check-stores.php](research/check-stores.php) na hostingu (4 běhy) a lokálně. Z domácí IP projde PHP i k Epicu a Loaded, z hostingu ne; G2A z hostingu propustil 1 ze 4 běhů — nespolehlivé. Ochrana se může kdykoli změnit i u dnes dostupných obchodů. |
| R8 | 2026-10-11 | **Žádný headless prohlížeč ani placené služby na obcházení ochrany** | Na sdíleném hostingu prohlížeč nespustíme; i lokálně Cloudflare headless Chrome poznal (projde jen viditelné okno). Placené scraping služby by u soukromé aplikace stály víc, než přinesou — data G2A a Loaded pokryje souhrnně gg.deals (R9). |
| R9 | 2026-10-11 | **Zdroje cen:** **IsThereAnyDeal API** pro oficiální obchody (Steam, Epic, Ubisoft, Blizzard a dalších ~30), **gg.deals API** pro souhrnnou cenu obchodů s klíči (zastoupí G2A a Loaded), **přímé stahování** jen PlayStation Store, Kinguin a Eneba. Klíče v `.env` (`GAMES_ITAD_API_KEY`, `GAMES_GGDEALS_API_KEY`) | ITAD je stabilní API s cenami po obchodech pro CZ — nahradí křehké stahování Steamu, Ubisoftu a Battle.netu a je jedinou cestou k Epicu. PlayStation a obchody s klíči ITAD nemá. gg.deals dává jen souhrn, ale je to jediná legální a stabilní cesta k G2A a Loaded. Povinný odkaz na gg.deals u jeho dat. |
| R10 | 2026-10-11 | **Katalog her z IsThereAnyDeal** — ITAD ID je identita hry (`games.itad_id`), Steam App ID druhotný klíč (pro gg.deals). Obchody bez ITAD se ke hře připojují **ručně URL konkrétního produktu** (`store_listings`), ne hledáním podle názvu | ITAD vrací pro hru model (ID, slug, název, typ, obrázky, Steam App ID, vývojáře, datum vydání) a převod z názvu i Steam App ID — vše se tak „provazuje“ přes jedno ID. Hledání podle názvu u obchodů s klíči vrací desítky variant (regiony LATAM/EU/Global, klíč/účet/dárek, „Mystery“ klíče — průzkum našel „CONTROL Resonant“ za 2,99 €, což byla náhodná hra). |
| R11 | 2026-10-11 | **Katalog her a ceny jsou společné, seznam sledovaných her a cílové ceny jsou osobní** (`watchlist_items` podle uživatele) | Cena hry je pro všechny stejná — stahovat ji pro každého zvlášť by násobilo požadavky na API a obchody. Každý účet ale sleduje jiné hry a má jinou mez. |
| R12 | 2026-10-11 | **Produkce na `game-watcher.rhsoft.cz`** — subdoména na stejném shared hostingu Websupport jako Počasí (Apache 2.4, PHP 8.4, MariaDB 11.4, cron jen jako volání URL) | Rozhodnutí zadavatele (O1). Hosting je ověřený diagnostikou z R7 (PHP 8.4.25, cURL 7.81, odchozí HTTPS funguje) a postup nasazení se přebírá z Počasí beze změn. |
| R13 | 2026-10-11 | **Upozornění e-mailem i přes Web Push**, uživatel si u účtu zvolí kanály. E-mail přes SMTP Websupportu (`MAIL_*` v `.env`), odeslání synchronně z cron URL (žádný `ShouldQueue`). Web Push převzatý z Počasí (`minishlink/web-push`, klíče VAPID v `.env`, service worker jen pro upozornění), odběr ale patří **přihlášenému uživateli**, ne anonymnímu zařízení | Rozhodnutí zadavatele (O2). E-mail dojde všude a zůstane v historii; push je okamžitý na telefonu — v Počasí osvědčený (na iPhonu jen v aplikaci přidané na plochu, iOS 16.4+). S přihlášením odpadá v Počasí nutné hledání zařízení podle adresy odběru i strop podstrčených odběrů. |
| R14 | 2026-10-11 | **Ceny se zobrazují v CZK, přepínač na EUR**; volba je u účtu (`users.display_currency`, výchozí CZK). Částky se dál **ukládají v měně zdroje**, převádí se až při zobrazení **denním kurzem ČNB** (kurzy devizového trhu — textový soubor zdarma a bez klíče), který cron stáhne jednou denně do `exchange_rates`; o víkendu a svátcích platí poslední vyhlášený kurz. Řazení a porovnání „kde je nejlevněji“ se počítá po převodu do zvolené měny; původní měna obchodu zůstává vidět u ceny | Rozhodnutí zadavatele (O3). Bez převodu nejde PlayStation (CZK) seřadit s ostatními (EUR). Uložení v měně zdroje zachová přesnou cenu — přepočet při uložení by do historie zapekl kurz daného dne a přepínač na EUR by pak převáděl tam a zpátky. ČNB je pro české ceny přirozený referenční kurz; cílová cena se ukládá i s měnou, ve které ji uživatel zadal. |
| R15 | 2026-10-11 | **Přidání hry hledáním v ITAD** (etapa 2): jedno pole pro **název** (`games/search`, 12 výsledků), **Steam App ID** nebo **odkaz na Steam** (`games/lookup` — přesná shoda). Hledá se až po odeslání formuláře, výsledky drží cache 1 h (klíč bez ohledu na velikost písmen), neúspěch se necachuje; nejvýš 20 hledání za minutu na účet (`games.catalog`). **Klíč ITAD jde v hlavičce `ITAD-API-Key`**, ne v URL. Výpadek, neplatný klíč nebo změněný tvar odpovědi = výjimka `ItadUnavailable`, stránka ukáže hlášku a podrobnost je v logu. Výsledky vyznačí hry, které uživatel už sleduje | ITAD povoluje 100 požadavků za 5 minut na klíč pro všechny účty — našeptávání při psaní by limit vyčerpalo. Odkaz na Steam je nejspolehlivější cesta k přesné hře; hledání podle názvu vrací i edice a DLC (u „CONTROL Resonant“ i Deluxe Edition a Upgrade). Adresa požadavku se objevuje v chybových hláškách HTTP klienta a v logu — klíč v ní by unikl. Cache ukládá pole, ne objekty (`cache.serializable_classes = false`). |
| R16 | 2026-10-11 | **Hra se do katalogu převezme při prvním přidání** (`games/info`: Steam App ID, vydání, vývojáři, vydavatelé, štítky, hodnocení, obal a banner) a ukládá se jako snímek s časem `info_refreshed_at`; další účet ji přidá bez volání ITAD. Detail hry vidí každý přihlášený, odebrání ze seznamu hru v katalogu nechá. Obrázky se nestahují, berou se přímo z CDN ITAD (`img-src https://assets.isthereanydeal.com` v CSP). Hlášky po akci přes `Inertia::flash()` (neukládá se do historie prohlížeče) a návrat na stránku, odkud akce přišla | Údaje hry se mění zřídka; obnova snímku se doplní s cronem (etapa 3, TODO). Hra v katalogu bude mít historii cen nezávisle na tom, kdo ji zrovna sleduje (R11). Kopírovat obrázky na sdílený hosting by zabíralo místo a CDN ITAD je rychlejší. Návrat zpět s hláškou dovolí z jednoho hledání přidat víc her za sebou. |
| R17 | 2026-10-11 | **CI v GitHub Actions zrušené** (`.github/workflows/ci.yml` smazán), stejně jako v Počasí (R59 tam); kontrola kvality je jen ruční před commitem (CODING_GUIDELINES, sekce 9) | Rozhodnutí správce — jeden vývojář, který kontroly pouští sám před každým commitem; workflow by se muselo průběžně udržovat (verze akcí GitHubu, konec Node 20, přechod `ubuntu-latest` na Ubuntu 26). |
| R18 | 2026-10-11 | **Ceny z API** (etapa 3): ITAD `games/prices` pro zemi CZ (dávka až 200 her v jednom POST) a gg.deals podle Steam App ID (dávka 100, region eu). **Nabídky zdroje se při každém stažení nahradí celé** (`current_prices`, klíč obchodu `itad-<id>` / `keyshops`) — obchod, který hru přestal nabízet, zmizí. **Historická minima přebírá ze zdrojů** (`price_lows`: ITAD `historyLow.all`, gg.deals `historicalKeyshops`), aplikace je nepočítá. **Denní historie** `price_history` drží nejnižší cenu dne po obchodech (upsert s `LEAST`). Částky v setinách (`Money`, žádný float), v DB jako decimal v měně zdroje. Ceny se stahují **jen hrám, které někdo sleduje**; nově sledovaná hra bez cen je dostane hned při přidání (výpadek zdroje přidání nezastaví). Zdroje na sobě nezávisí — výpadek jednoho zaloguje klient a druhý proběhne. **Klíč gg.deals jde jen v URL** (hlavičku API nezná) — `GgDealsClient` ho z každé zprávy do logu maže a jeho výjimka nenese původní výjimku. Odkazy do obchodů jsou partnerské odkazy ITAD (`rel="sponsored"`, nesmí se měnit); u cen je uvedený zdroj s odkazem na ITAD a gg.deals | Dávky a jen sledované hry drží požadavky hluboko pod limity API. Historické minimum z vlastních dat by bylo jen od začátku sledování — zdroje ho znají za celou dobu. Denní historie místo každého stažení: graf (etapa 7) potřebuje den, ne šest bodů denně. Bez okamžitého stažení by nově přidaná hra až 4 hodiny ukazovala „bez ceny“. Edice se stejným Steam App ID dostanou od gg.deals stejnou cenu — rozlišit je neumí. |
| R19 | 2026-10-11 | **Cron URL `/cron/ceny?token=…`** (`routes/cron.php` mimo skupinu `web`, token `GAMES_CRON_TOKEN`, špatný = 404, rate limit): kroky **kurzy ČNB → ceny → úklid prošlé cache**, každý zvlášť (chyba se zaloguje, místo řádku „chyba“, URL vrátí 500; výpadek zdroje cen také 500), celé volání drží `Cache::lock` (10 min), souběh odpoví 409. **Cron ve WebAdminu každé 4 hodiny.** Kurz ČNB se stahuje, jen když uložený není dnešní, a nejvýš jednou za hodinu (o víkendu platí páteční lístek) | Vzor z projektu Počasí (R14, R27, R58 tam) — osvědčený na stejném hostingu. Šest stažení denně stačí na zachycení slev s rezervou v limitech API a prošlá cache hledání se průběžně uklidí. Kurz před cenami, aby přehled hned převáděl novým kurzem. |
| R20 | 2026-10-12 | **PlayStation Store ve stejném výpisu cen jako samostatné položky.** Produkt se ke hře připojí **odkazem** (produkt i stránka hry — koncept; z konceptu se vezme jeho výchozí produkt), jiný hostitel nebo tvar adresy se odmítne bez požadavku (SSRF). Stahuje se **stránka produktu v české verzi** (`/cs-cz/product/<ID>`, hlavičky prohlížeče), data z JSON skriptů `env:*` (cache Apolla): `GameCTA` druhu ADD_TO_CART = běžná cena (`discountedValue`, `basePriceValue`, `endTime`), UPSELL_PS_PLUS_DISCOUNT = **cena s PS Plus**, UPSELL_PS_PLUS_GAME_CATALOG = **hra v katalogu PS Plus Extra**. Cena v `current_prices` (klíč `psn-<id propojení>`, `platform` = playstation, `edition`, `member_amount`, `member_included`), řadí se s obchody pro PC podle běžné ceny; na kartě přehledu je PS Store i zvlášť („PlayStation od …“). Cron stahuje produkty po jednom s pauzou 0,5 s; produkt, který z obchodu zmizí, ztratí cenu, ale propojení zůstane | Zadání: „zobrazovat cenu bez i s PS Plus, ve stejném výpisu, ale jako solo položky“. ITAD PlayStation nepokrývá; stránka produktu z hostingu projde (R7) a nese ceny v CZK. Interní GraphQL obchodu by potřeboval hash dotazu, který se mění s verzí webu — stránka je stabilnější. Odkaz místo hledání podle názvu (R10): koncept má víc produktů (PS4, PS5, edice, upgrady). Katalog PS Plus není cena — zdarma je jen pro tier Extra, proto příznak místo částky. Formát není veřejné API — parser při změně vrátí null a nic neuloží. |
| R21 | 2026-10-12 | **Pokročilé filtry přehledu** — hledání v názvu, platforma (PC / PlayStation), výběr obchodů (štítky s počtem her; nejlevnější nabídka jen z vybraných, obchody s klíči jako jedna skupina; **zrušený v R25**), druh (hry / DLC), cena do (v měně účtu), sleva aspoň (25 / 50 / 75 %), jen ve slevě, „mám PS Plus“ (u PS Store počítá cenu pro předplatitele) a řazení (přidané, název, cena, sleva, vydání). **Filtruje a řadí server** (`WatchlistView`), stav je v adrese (sdílet, zpět), neplatná hodnota se ignoruje; prohlížeč posílá změny s prodlevou 300 ms a načítá jen data přehledu | Zadání: „nad vším hezké pokročilé filtry“. Sledovaných her jsou desítky — výpočet všech karet na serveru je levný a „nejlevněji“ zůstává na jednom místě (PriceBoard), komponenta nic nepočítá (CODING_GUIDELINES, sekce 5). Adresa místo stavu v prohlížeči — filtr jde poslat odkazem a funguje tlačítko zpět. Na mobilu jsou volby sbalené za tlačítkem, aby nepřekryly výpis. |
| R22 | 2026-10-12 | **Exkluzivity PlayStationu** — ITAD je zná jen jako zástupný záznam (bez druhu, obrázků, data vydání a obchodů; ověřeno u Saros, Ghost of Yōtei, Gran Turismo 7) a `games/search` je nevrací. **Hledání podle názvu proto navíc volá `games/lookup?title=`** (přesný název bez ohledu na velikost písmen) a takovou hru dá na první místo, bez duplicity. Hra zůstává v katalogu pod ITAD ID (R10 platí). **Připojení PS Store doplní chybějící údaje** — obal (`PORTRAIT_BANNER`, šířka 400 px přes `?w=`), banner (`SIXTEEN_BY_NINE_BANNER`, 600 px), datum vydání a vydavatele; údaje z ITAD se nepřepisují. CSP povoluje `https://image.api.playstation.com`. **Přijímá se i odkaz na stránku hry na `www.playstation.com/<jazyk>/games/<slug>/`** — ze stránky se vezme první odkaz do obchodu (standardní edice), případně koncept; stránka, která odkaz v HTML nemá (vykresluje se až v prohlížeči), skončí chybou s radou vložit odkaz z obchodu. Parser cen bere jen vyjmenované druhy tlačítek — zkušební verze s PS Plus Premium (`UPSELL_PS_PLUS_TRIAL`, cena 0) není cena ani katalog | Uživatel hledal „Saros“ a nenašel nic. Druhý dotaz na ITAD je levný (výsledek v cache 1 h) a nevyžaduje vlastní katalog mimo ITAD — hra má stálou identitu i kdyby později vyšla na PC. Obrázky a údaje z PS Store dělají kartu použitelnou; odkaz z playstation.com je to, co uživatel najde jako první. Originály obrázků mají až 3840 px, obrázkové API je umí zmenšit. Zkušební verze by bez whitelistu tlačítek vypadala jako „S PS Plus 0 Kč“ a „V katalogu PS Plus“. |
| R23 | 2026-10-12 | **Obchody ve výsledcích hledání** — po hledání jde na ITAD **jeden dávkový dotaz `games/prices/v3`** na všechny nalezené hry (`DescribeAvailability`), výsledek v cache jako hledání (`search_cache_seconds`). U výsledku se ukáže nejvýš `max_shop_badges` obchodů — napřed ty z `highlight_shops` v daném pořadí, zbytek abecedně a počtem „+N“ — a nejnižší cena převedená do měny účtu. **PlayStation** se ukáže jen u hry, která už je v katalogu s připojeným produktem PS Store (R20); bez připojení to aplikace neví — PS Store se kvůli hledání nevolá. Hra bez nabídky má „V obchodech pro PC se neprodává“. **Výpadek cen výsledky neskryje** — jen se ukážou bez obchodů | Uživatel chtěl při přidávání vidět, co je ze Steamu, Epicu, PS. Ceny ITAD vrací pro celou dávku najednou (limit 100 požadavků / 5 min, R15), takže hledání stojí o jeden dotaz víc a podruhé nic. Prohledávat PS Store podle názvu nejde spolehlivě (R10) a z hostingu by každé hledání stahovalo stránky obchodu. |
| R24 | 2026-10-12 | **Hlídaná platforma u sledované hry** — `watchlist_items.platform` (NULL = všude, `pc`, `playstation`); patří sledování, ne hře, takže každý účet může stejnou hru hlídat jinde. Hra hlídaná jen na jedné platformě má na přehledu štítek a karta, „nejlevněji“, řazení i cenové filtry počítají jen s cenami té platformy (`PriceFilter::restrictedTo()`); u „jen PlayStation“ se nepočítají ani obchody s klíči (klíče jsou pro PC). Filtr přehledu na jinou platformu takovou hru skryje. Hra jen na PlayStationu bez připojeného PS Store vyzve „Připoj PlayStation Store“. Detail ukazuje dál všechny obchody, ostatní platformy jen ztlumí. Volí se ve výsledcích hledání (tlačítko „Jen na PS“ vedle „Sledovat“ — bez připojeného PS Store vede rovnou na detail k připojení; **jen u hry, která je na PlayStationu, nebo bez obchodů pro PC** — u hry jen pro PC by tlačítko klamalo). **Na PlayStationu je hra s připojeným PS Store nebo se stránkou na www.playstation.com** — ITAD platformy nezná a hledání v PS Store vykresluje až prohlížeč, proto se u her z výsledků (ne DLC a balíčků) ověří dotazem HEAD adresa `/cs-cz/games/<slug z názvu>/` (souběžně nejvýš 4, výsledek v cache týden, výpadek se neukládá ani nezastaví hledání). Taková hra má ve výsledcích štítek PlayStation a „Jen na PS“ její stránku rovnou připojí (produkt přes koncept). Hra s jinou adresou stránky (edice v názvu) se nepozná — pak zbývá vložit odkaz z PS Store a na detailu (Všude / Jen PC / Jen PlayStation). **Přidat hru jde i podle odkazu z PS Store nebo ze stránky hry na playstation.com**: ze stránky produktu se vezme název bez ochranných známek a označení platformy, podle něj se hledá v ITAD jako jindy (i přesný název, R22) a při přidání se produkt připojí a hra se hlídá jen na PlayStationu. Stránka hry na playstation.com, která odkaz do obchodu v HTML nemá (Until Dawn), dá produkt přes ID konceptu z `<meta name="conceptId">`; dostupnost www.playstation.com z hostingu ověřuje `docs/research/check-stores.php` (řádek „PS web“). Přidání hry (`POST /hry`) má limit jako propojení — volá ITAD a s odkazem i PS Store | Uživatel hraje některé hry jen s manželkou na PS5 — PC cena by na kartě byla „nejlevněji“, i když by hru na PC nekoupil. Volba u sledování je jednodušší než filtr přehledu, který platí pro všechny hry najednou. Přidání podle odkazu z PS Store je pro hru na PlayStation jeden krok místo hledání, přidání a připojení na detailu; identita hry zůstává ITAD ID (R10), takže hra, která vyjde i na PC, se nerozdělí. Cílová cena (etapa 6) se bude řídit hlídanou platformou. |
| R25 | 2026-10-12 | **Filtr podle žánru a počtu hráčů místo filtru obchodů** — výběr obchodů se nepoužíval a zrušil se (filtr, `PriceFilter` jen platforma a PS Plus). **Žánry a režimy ze Steamu** (`appdetails` bez klíče, `l=czech`, filtr `categories,genres`): žánry jako české názvy do `games.genres`, kategorie převedené na režimy `GameMode` (pro jednoho, pro víc, co-op, co-op online, couch co-op = sdílená/rozdělená obrazovka, víc hráčů u jedné obrazovky) do `games.modes`. Stahují se při přidání hry a cron (krok „žánry a režimy“) doplňuje sledované hry bez údajů nebo starší než 30 dní, nejvýš 20 za běh s pauzou (limit Steamu ~200 požadavků / 5 min); hra, kterou Steam nezná, se označí a znovu se neptá; výpadek Steamu běh cronu neoznačí jako chybný. **Počet hráčů z PS Store** (`compatibilityNoticesByPlatform`: `NO_OF_PLAYERS` = u jedné konzole, `NO_OF_NETWORK_PLAYERS_PS_PLUS` = online) do `store_listings` při připojení i každé obnově ceny — u jedné konzole 1 = hra pro jednoho, 2+ = víc hráčů u jedné obrazovky. Filtr **Hráči: 1 / 2+ / Couch co-op** (`PlayerFilter`) bere režimy ze Steamu i PS Store; **Žánr** jsou štítky s počtem her, stačí jeden z vybraných. Hra bez údajů s takovým filtrem vypadne. Detail hry ukazuje žánr a hráče | Uživatel hledá hlavně hry pro dva a na gauč (hraje s manželkou na PS5), výběr obchodů nepotřeboval. ITAD má jen pár nejčastějších štítků bez co-op údajů; Steam má přesné kategorie a české žánry zdarma a z hostingu je dostupný (R7). PS Store počet hráčů má, žánry ne (vrací „Unique“) — proto kombinace; exkluzivity bez Steamu mají aspoň hráče z PS Store. |
| R26 | 2026-10-12 | **Upozornění na ceny** (etapa 6). **Cena** = nejlevnější nabídka hlídané platformy (R24) **včetně obchodů s klíči**, s PS Plus u účtu (`users.has_ps_plus`) cena pro předplatitele, v měně zobrazení účtu. **S cílovou cenou** (zadaná v měně účtu, převádí se kurzem ČNB): upozornit, když je cena na cíli nebo pod ním. **Bez cílové ceny**: upozornit, když je nabídka ve slevě a na historickém minimu svého zdroje — oficiální obchody minimum z ITAD, obchody s klíči z gg.deals (slevu neuvádějí, rozhoduje jen minimum), PS Store z vlastní `price_history`, až je aspoň 30 dní dlouhá. **Stejnou cenu podruhé neposílat**: pamatuje se poslední upozorněná cena; nové upozornění až při nižší ceně, nebo když podmínka mezitím přestala platit (pak se paměť smaže). **Kanály**: e-mail (`users.notify_email`, výchozí zapnutý; jeden e-mail za běh se všemi hrami, SMTP Websupportu synchronně) a zařízení s Web Push (`push_subscriptions` podle účtu; víc než 3 hry najednou jedna souhrnná zpráva). Účet bez kanálu se přeskočí beze změny stavu. Upozornění se označí jako odeslané, jen když ho doručil aspoň jeden kanál — jinak ho zkusí další běh a cron vrátí 500. Krok cronu jde po cenách a žánrech. **Slevy dne** = sekce nad přehledem: hry, jejichž nejlevnější nabídka (bez PS Plus — historie ji nezná) klesla proti včerejšímu minimu z `price_history` aspoň o 5 % | Rozhodnutí zadavatele: bez cíle historické minimum (málo zpráv, a když přijdou, stojí za to), klíče se počítají, slevy dne jako sekce na přehledu. Pravidlo „jen ve slevě“ u minima brání upozornění na hry, které se nikdy neprodávaly levněji (minimum = běžná cena). Vlastní historie PS Store je zpočátku krátká — první stažená cena by byla „minimem“. Paměť poslední ceny místo „upozorněno ano/ne“ umožní upozornit znovu při dalším poklesu. Haléřové výkyvy ceny v eurech by bez prahu dělaly „−0 %“ slevy dne. |
| R27 | 2026-10-12 | **Aplikace na plochu (PWA) kvůli upozornění na iPhonu** — manifest `/manifest.webmanifest` (název, ikony 192 a 512 px z `logo.svg`, `display: standalone`, tmavá barva) a service worker `/sw.js` jen pro upozornění (bez cache a offline režimu), obojí bez přihlášení. Vzor z Počasí. Klíče VAPID generuje `games:push-keys`; bez nich se upozornění na zařízení nenabízí. Adresa odběru jen u známých push služeb (FCM, Mozilla, Apple, Windows — SSRF), nejvýš 10 zařízení na účet | Web Push na iPhonu funguje jen v aplikaci přidané na plochu (iOS 16.4+). Service worker musí ležet v kořeni webu, proto ho vydává server, ne Vite. Offline režim by ukazoval staré ceny. |
| R28 | 2026-10-12 | **Graf vývoje ceny** (etapa 7) na detailu hry. Hodnota dne = nejnižší cena, která ten den platila (místní den). Řady: **nejlevnější obchod pro PC a Steam z historie ITAD** (`games/history/v2` — změny ceny po obchodech, zpětně `chart.history_days` = 730 dní; cena obchodu platí od změny do další), **obchody s klíči a produkty PS Store z vlastní `price_history`** (gg.deals ani PS Store historii nedávají — sbírá se od nasazení cronu, pod grafem „sledujeme od“). K tomu **historické minimum obchodů s klíči z gg.deals** jako vodorovná čárkovaná řada (jde vypnout v legendě) — srovnání, dokud je vlastní historie klíčů krátká. Vše v měně účtu **dnešním kurzem ČNB** (historické kurzy se neukládají). Historie ITAD v cache 6 h (jako pole, R13), výpadek ITAD graf neskryje. Graf je **odložený prop Inertia** a komponenta s ECharts (`echarts` 6.1 + `vue-echarts` 8.3 jako v Počasí, SVG, jen potřebné části) se načítá asynchronně — samostatný blok ~190 kB gzip jen na detailu. Období 3 měsíce / rok / vše, vodorovná čára cílové ceny, řady jiné platformy, než se hra hlídá (R24), jsou na začátku vypnuté v legendě | Vlastní historie by graf zaplnila až za měsíce — ITAD má historii změn po obchodech zdarma v rámci API klíče a jedním požadavkem na hru. Odložené načtení drží detail rychlý i při prázdné cache. Přepočet dnešním kurzem zkreslí staré ceny o pohyb kurzu (jednotky procent), ale umožní porovnat PC (EUR) s PS Store (CZK) v jedné ose. |
