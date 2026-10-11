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

### Co už umí (etapy 1–2)
- Přihlášení e-mailem a heslem s ochranou proti hádání hesla, odhlášení, „zůstat přihlášen“ (R4)
- Zakládání účtů příkazem `games:create-user`, pro produkci jako SQL (`--sql`)
- Retrofuturistický vzhled (R6)
- **Přidání hry** — hledání v katalogu IsThereAnyDeal podle názvu, Steam App ID nebo odkazu na Steam (R15)
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

### Dnes (etapy 1–2)

| Tabulka | Obsah |
|---|---|
| `users` | účty — jméno, e-mail, hash hesla, `remember_token`; bez ověřování e-mailu a obnovy hesla (R4) |
| `cache`, `cache_locks` | databázová cache a zámky (R2), výsledky hledání v ITAD (R15) |
| `games` | katalog z ITAD (R10): `itad_id` (UUID, unikátní — identita), `slug` (unikátní, adresa detailu), `title`, `type` (game/dlc…), `is_mature`, `steam_app_id` (prázdné mimo Steam), `release_date`, `developers` / `publishers` / `tags` / `reviews` (JSON), `boxart_url`, `banner_url`, `itad_url`, `info_refreshed_at` |
| `watchlist_items` | osobní seznam (R11): `user_id` + `game_id` unikátně, mazání kaskádou s účtem i hrou |

Tabulky `sessions`, `password_reset_tokens` ani tabulky fronty nejsou (R2, R4, R5).

### Návrh pro etapy 3–6 (upřesní se při implementaci)

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
| `store_listings` | `game_id`, `source` (playstation/kinguin/eneba), `external_id`, `url`, `label` („Steam klíč EU“) | konkrétní produkt v obchodě bez ITAD; zadává se URL, ne hledáním podle názvu (R10) |
| `current_prices` | `game_id`, `shop` (steam, epic, gog…, keyshops, playstation…), `source` (itad/ggdeals/přímo), `amount`, `regular_amount`, `currency`, `cut`, `url`, `fetched_at` | co se ukazuje v přehledu |
| `price_history` | `game_id`, `shop`, `day`, `amount`, `currency` | graf a minima; den, ne každé stažení (jako `daily_summaries` v Počasí) |
| `watchlist_items` *(rozšíření v etapě 6)* | `target_price`, `currency`, `notified_at` | mez pro upozornění (R11, R13) |

**Měny (R14):** ITAD (CZ) a gg.deals (eu) vrací EUR, PlayStation Store CZK, Kinguin
a Eneba EUR (Eneba i CZK). Částky se ukládají **v měně zdroje** — porovnání napříč
měnami jen přes převod, nikdy přímo. Převádí se při zobrazení kurzem ČNB:

| Tabulka | Klíčové sloupce | K čemu |
|---|---|---|
| `exchange_rates` | `day`, `currency` (unikátně spolu), `rate` (CZK za 1 jednotku) | denní kurz ČNB; stahuje ho cron jednou denně |
| `users.display_currency` | `CZK` / `EUR` | volba měny zobrazení u účtu (výchozí CZK) |

**Upozornění (R13):** odběry Web Push (`push_subscriptions` podle uživatele — vzor
z Počasí) a volba kanálů u účtu (e-mail, push).

---

## 5. Toky dat (plán)

```
uživatel ──hledá (název / Steam App ID / URL Steamu)──▶ ITAD search nebo lookup (cache 1 h) ──▶ výsledky
uživatel ──Sledovat──▶ ITAD info (jen hra mimo katalog) ──▶ games + watchlist_items   (R15, R16)
uživatel ──vloží URL produktu PSN / Kinguin / Eneba──────▶ ověření hostitele a ID ──▶ store_listings

cron hostingu (URL s tokenem) ──▶ /cron/refresh-prices?token=…
      ├─ ITAD prices (CZ, po 200 hrách) ─────────────────┐
      ├─ gg.deals (eu, podle Steam App ID) ───────────────┼──▶ current_prices + price_history
      ├─ PlayStation / Kinguin / Eneba (po jednom listingu) ┘
      ├─ kurz ČNB (1× denně) ──▶ exchange_rates
      └─ porovnání s watchlist_items ──▶ e-mail (SMTP Websupportu) + Web Push   (R13)
```

Jako v Počasí: hosting umí jen zavolat URL, žádný scheduler ani fronta (R2).
Kroky cronu na sobě nezávisí a souběh hlídá zámek — výpadek jednoho zdroje
nesmí zastavit ostatní.

---

## 6. Etapy

| # | Obsah | Stav |
|---|---|---|
| 1 | **Kostra** — Laravel 13 + Inertia + Vue v Dockeru, dokumentace, přihlášení bez registrace (R4), zakládání účtů příkazem, retrofuturistický vzhled (R6), CI, nasazovací skript a SQL skript výchozího schématu | hotovo 2026-10-11, nenasazeno |
| 2 | **Katalog her z ITAD** — přidání hry podle názvu, Steam App ID nebo URL Steamu; detail hry (obal, vydání, vývojář, hodnocení, štítky, odkazy); osobní seznam sledovaných her na přehledu (R10, R11, R15, R16) | hotovo 2026-10-11, nenasazeno |
| 3 | **Ceny z API a měny** — ITAD (oficiální obchody, CZ) a gg.deals (obchody s klíči, eu); cron URL s tokenem, zámek a nezávislé kroky; `current_prices` a `price_history`; kurz ČNB a zobrazení v CZK / EUR s přepínačem (R14); přehled s nejnižší cenou a historickým minimem | |
| 4 | **První nasazení** na `game-watcher.rhsoft.cz` (R12) — subdoména, databáze, SQL skripty, první účet, cron | |
| 5 | **Přímé zdroje** — PlayStation Store, Kinguin, Eneba: připojení produktu ke hře přes URL, stahování v cronu, testy nad zachycenými odpověďmi, hlídání změny formátu | |
| 6 | **Hlídání cen** — cílová cena, upozornění e-mailem a Web Push (R13), „slevy dne“ ve sledovaných | |
| 7 | **Historie cen** — graf vývoje ceny (ECharts) | |

---

## 7. Otevřené otázky

| # | Otázka | Stav |
|---|---|---|
| O1 | **Doména a hosting** — stejný Websupport jako Počasí? Subdoména? | vyřešeno (R12): `game-watcher.rhsoft.cz` na Websupportu |
| O2 | **Kanál upozornění** — e-mail (SMTP hostingu), Web Push jako v Počasí, nebo Telegram/Discord bot? | vyřešeno (R13): e-mail i Web Push |
| O3 | **Měna zobrazení** — ceny v měně obchodu, nebo vše přepočítat na CZK? | vyřešeno (R14): CZK kurzem ČNB, přepínač na EUR |
| O4 | **Stačí pro G2A a Loaded souhrnná cena z gg.deals?** Konkrétní cenu z hostingu nezískáme (R7, R8) | |
| O5 | **Které edice a regiony u obchodů s klíči?** Navrženo: jen klíče platné v ČR/EU pro PC (Steam), bez účtů, darů a „Mystery“ klíčů — gg.deals podle všeho filtruje podobně | |
| O6 | **Jak často stahovat ceny** — návrh 2× až 4× denně; limit ITAD 100 požadavků / 5 min s dávkou 200 her nevadí, přímé zdroje stahovat šetrně (po jednom listingu, s pauzou) | |

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
