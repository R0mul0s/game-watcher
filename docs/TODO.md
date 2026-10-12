<!--
  Odložené úkoly — Game Watcher
  @author Roman Hlaváček
  @created 2026-10-11
-->

# TODO — odložené úkoly

Věci, na kterých jsme se domluvili, ale záměrně jsme je odložili, a nápady
zapsané na později. Co se rozhodne a udělá, se odsud smaže a popíše
v [PLAN.md](PLAN.md); větší celky se z toho stávají etapou.

---

## Další obchody a zdroje

**Odkud:** R7 a R9 v PLAN.md.

- **Xbox / Microsoft Store** a **Nintendo eShop** — ITAD pokrývá Microsoft Store pro PC; konzolové
  obchody by potřebovaly vlastní průzkum jako v R7
- **GOG, GreenManGaming, Fanatical, Humble** přicházejí z ITAD „zadarmo“ — rozhodnout, jestli je
  v přehledu ukazovat všechny, nebo jen vybrané obchody (nastavení uživatele)
- **Kinguin a Eneba přímo** (zbytek etapy 5) — odloženo spolu s O4 (2026-10-12): zatím stačí
  souhrnná cena obchodů s klíči z gg.deals; přímé zdroje by přidaly jen další křehké parsery
- **Konkrétní cena G2A a Loaded** — z hostingu nejde (R7, R8). Kdyby souhrn z gg.deals nestačil
  (O4): malý pomocník na domácím PC (Plánovač úloh + skutečný prohlížeč), který ceny pošle na
  hosting přes URL s tokenem. Složitější provoz — až když bude opravdu potřeba
- **PlayStation Store — další** (R20 je hotové): výběr edice přímo z produktů konceptu (dnes se bere
  výchozí produkt, jinou edici připojí odkaz na produkt); rozlišit katalog PS Plus Extra a Premium
  (`tierLabel`); hry zdarma s PS Plus Essential (měsíční hry) jako upozornění
- **Epic zdarma** — týdenní hry zdarma z Epicu (`freeGamesPromotions`) jako upozornění

## Katalog a seznam her

**Odkud:** etapa 2 v PLAN.md, R15, R16.

- **obnova údajů her** — `info_refreshed_at` je připravené; další krok cron URL (R19) by obnovil snímek
  starší než např. 30 dní (datum vydání neohlášených her, hodnocení včetně Metascore na kartě a řazení podle Metacritic — R32, obrázky). Pozor na limit ITAD
  (R15) — po pár hrách za běh
- **import Steam wishlistu** (ITAD umí synchronizaci wishlistu i „waitlist“ — přes OAuth uživatele;
  zatím zbytečné, pár her se přidá ručně)
- sdílení seznamu mezi účty (rodina) — dnes má každý vlastní (R11)
- DLC a edice (Deluxe, Gold) — ITAD je vede jako samostatné hry; rozhodnout, jestli je u hry
  seskupit

## Upozornění

**Odkud:** R13 v PLAN.md, etapa 6.

- souhrnný přehled „co zlevnilo za týden“ (e-mailem jednou týdně)
- upozornění na Epic zdarma a měsíční hry PS Plus (viz Další obchody)
- historické minimum PS Store z cizího zdroje (dnes vlastní historie, platí až po 30 dnech, R26)

## Přehled a zobrazení

- **PWA** — manifest a service worker pro upozornění jsou (R27); zkratky aplikace a odznak s počtem slev jako v Počasí zatím ne
- **graf ceny**: historické kurzy ČNB (dnes se přepočítává dnešním kurzem, R28), historie obchodů s klíči odjinud než z vlastních záznamů, značky slev (začátek a konec akce)
- porovnání regionů (stejná hra na PS Store v CZ vs. TR/AR) — zajímavé, ale na hranici pravidel obchodů
