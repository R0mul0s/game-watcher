# Game Watcher — instrukce pro AI agenty

Instrukce pro tento projekt jsou v **[CLAUDE.md](CLAUDE.md)**, závazná pravidla
v [docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md) a zadání s logem
rozhodnutí v [docs/PLAN.md](docs/PLAN.md).

Tento soubor je jen rozcestník, aby existoval jeden zdroj pravdy.

> **Pozor:** Projekt běží výhradně v Dockeru — PHP, Composer ani Node se lokálně
> neinstalují. Příkazy se pouštějí přes `docker compose exec app …`,
> viz [CLAUDE.md](CLAUDE.md).
>
> Produkce je sdílený hosting bez SSH: **každá nová migrace potřebuje SQL skript
> v `deploy/`** a nic nesmí záviset na frontě nebo scheduleru. Podrobnosti v CLAUDE.md,
> sekce Produkce.
>
> Epic, G2A a Loaded **z hostingu nejsou dostupné** (R7) — ceny jdou přes
> IsThereAnyDeal a gg.deals (R9). Testy nikdy nevolají skutečná API.
