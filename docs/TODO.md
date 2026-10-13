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
- **PlayStation Store — další** (R20, R35 jsou hotové): výběr edice přímo z produktů konceptu (dnes se
  bere výchozí produkt, jinou edici připojí odkaz na produkt); úroveň PS Plus u účtu (Essential /
  Extra / Premium) — pak by „zdarma v katalogu“ mohlo počítat cenu 0 jako měsíční hry
- **Epic zdarma** — týdenní hry zdarma z Epicu (`freeGamesPromotions`) jako upozornění; Epic je
  z hostingu blokovaný (R7) — endpoint nejdřív ověřit `check-stores.php`

## Katalog a seznam her

**Odkud:** etapa 2 v PLAN.md, R15, R16.

- **import Steam wishlistu** (ITAD umí synchronizaci wishlistu i „waitlist“ — přes OAuth uživatele;
  zatím zbytečné, pár her se přidá ručně)
- sdílení seznamu mezi účty (rodina) — dnes má každý vlastní (R11)
- DLC a edice (Deluxe, Gold) — ITAD je vede jako samostatné hry; rozhodnout, jestli je u hry
  seskupit

## Upozornění

**Odkud:** R13 v PLAN.md, etapa 6.

- upozornění na Epic zdarma (viz Další obchody)
- historické minimum PS Store z cizího zdroje (dnes vlastní historie, platí až po 30 dnech, R26)

## Přehled a zobrazení

- **PWA** (R27, R37 jsou hotové) — výzva „Přidat na plochu“ v nastavení (dialog prohlížeče na Androidu,
  návod na iPhonu); písma na stránce offline (dnes systémové — písma se načítají přes skript aplikace)
- **graf ceny**: historické kurzy ČNB (dnes se přepočítává dnešním kurzem, R28), historie obchodů s klíči odjinud než z vlastních záznamů, značky slev (začátek a konec akce)
- porovnání regionů (stejná hra na PS Store v CZ vs. TR/AR) — zajímavé, ale na hranici pravidel obchodů
