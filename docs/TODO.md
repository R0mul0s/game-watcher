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
- **Konkrétní cena G2A a Loaded** — z hostingu nejde (R7, R8). Kdyby souhrn z gg.deals nestačil
  (O4): malý pomocník na domácím PC (Plánovač úloh + skutečný prohlížeč), který ceny pošle na
  hosting přes URL s tokenem. Složitější provoz — až když bude opravdu potřeba
- **PlayStation Plus** — PSN vrací i cenu pro předplatitele (`serviceBranding`, `upsell`) a hry
  v katalogu PS Plus; ukazovat zvlášť
- **Epic zdarma** — týdenní hry zdarma z Epicu (`freeGamesPromotions`) jako upozornění

## Katalog a seznam her

**Odkud:** etapa 2 v PLAN.md, R15, R16.

- **obnova údajů her** — `info_refreshed_at` je připravené; cron (etapa 3) by obnovil snímek
  starší než např. 30 dní (datum vydání neohlášených her, hodnocení, obrázky). Pozor na limit ITAD
  (R15) — po pár hrách za běh
- hledání i podle **odkazu na IsThereAnyDeal** (`isthereanydeal.com/game/<slug>/`) — ITAD nemá
  lookup podle slugu; šlo by přes `games/search` se slugem jako názvem
- řazení a filtr přehledu (podle názvu, data vydání, později slevy)

- **import Steam wishlistu** (ITAD umí synchronizaci wishlistu i „waitlist“ — přes OAuth uživatele;
  zatím zbytečné, pár her se přidá ručně)
- sdílení seznamu mezi účty (rodina) — dnes má každý vlastní (R11)
- DLC a edice (Deluxe, Gold) — ITAD je vede jako samostatné hry; rozhodnout, jestli je u hry
  seskupit

## Upozornění

**Odkud:** R13 v PLAN.md, etapa 6.

- souhrnný přehled „co zlevnilo za týden“ (e-mailem jednou týdně)
- upozornění na **historické minimum** (ITAD vrací `historyLow`), nejen na cílovou cenu

## Přehled a zobrazení

- **PWA / aplikace na ploše telefonu** — manifest jako v Počasí (R43 tam)
- porovnání regionů (stejná hra na PS Store v CZ vs. TR/AR) — zajímavé, ale na hranici pravidel obchodů

## Provoz

- provozní stránka „stav zdrojů“: kdy se který zdroj naposledy úspěšně stáhl, kolik cen, chyby —
  změna formátu obchodu se jinak pozná až podle chybějících cen
- monitoring cron URL externí službou (UptimeRobot jako v Počasí)
