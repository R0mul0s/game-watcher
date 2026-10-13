<?php

/**
 * Čeština ve hře.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Ze Steamu (`supported_languages`, hvězdička = plná zvuková podpora) a z PS
 * Store (`screenLanguages` = titulky a rozhraní, `spokenLanguages` = dabing),
 * R47. Neznámou češtinu (zdroj jazyky neuvádí) nese null, ne None. Jako filtr
 * přehledu znamená „aspoň tohle“ — Text najde i hru s dabingem.
 */
enum CzechSupport: string
{
    case None = 'none';

    // Rozhraní a titulky
    case Text = 'text';

    // Dabing (u her s dabingem bývají i titulky)
    case Voice = 'voice';

    /**
     * Pořadí od nejslabší podpory — pro výběr nejlepší a porovnání s filtrem.
     */
    private function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Text => 1,
            self::Voice => 2,
        };
    }

    /**
     * Nejlepší čeština z několika zdrojů; null, když ji žádný nezná.
     */
    public static function best(?self ...$values): ?self
    {
        $best = null;
        foreach ($values as $value) {
            if ($value !== null && ($best === null || $value->rank() > $best->rank())) {
                $best = $value;
            }
        }

        return $best;
    }

    /**
     * Odpovídá čeština hry filtru (aspoň tahle úroveň)? Neznámá neodpovídá.
     */
    public function matches(?self $support): bool
    {
        return $support !== null && $support->rank() >= $this->rank();
    }
}
