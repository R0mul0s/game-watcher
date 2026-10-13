<?php

/**
 * Žánry, režimy a čeština hry ze Steamu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Domain\Steam;

use App\Enums\CzechSupport;
use App\Enums\GameMode;

/**
 * Z odpovědi appdetails (filtr basic, categories, genres; jazyk čeština) — žánry
 * jako české názvy, kategorie převedené na režimy hry (R25), čeština ze
 * seznamu jazyků (R47). Ostatní kategorie (achievementy, podpora ovladače…)
 * se zahazují.
 */
final readonly class SteamAppDetails
{
    /**
     * Názvy češtiny v `supported_languages` — Steam je píše v jazyce požadavku
     * (games.sources.steam.language), anglický název pro jistotu taky.
     */
    private const CZECH_NAMES = ['čeština', 'czech'];

    /** Značka plné zvukové podpory jazyka (`<strong>*</strong>`). */
    private const FULL_AUDIO_MARK = '*';

    /** Za tímhle oddělovačem je jen vysvětlivka hvězdičky. */
    private const FOOTNOTE_SEPARATOR = '<br>';

    /**
     * @param  list<string>  $genres
     * @param  list<GameMode>  $modes
     * @param  CzechSupport|null  $czech  null = Steam jazyky neuvádí
     */
    public function __construct(
        public array $genres,
        public array $modes,
        public ?CzechSupport $czech = null,
    ) {}

    /**
     * Údaje z části `data` odpovědi appdetails.
     *
     * @param  array<mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        $genres = [];
        foreach (is_array($data['genres'] ?? null) ? $data['genres'] : [] as $genre) {
            $name = is_array($genre) && is_string($genre['description'] ?? null) ? trim($genre['description']) : '';
            if ($name !== '') {
                $genres[$name] = $name;
            }
        }

        $categories = GameMode::steamCategories();
        $modes = [];
        foreach (is_array($data['categories'] ?? null) ? $data['categories'] : [] as $category) {
            $mode = is_array($category) && is_int($category['id'] ?? null) ? ($categories[$category['id']] ?? null) : null;
            if ($mode !== null) {
                $modes[$mode->value] = $mode;
            }
        }

        return new self(array_values($genres), array_values($modes), self::czech($data['supported_languages'] ?? null));
    }

    /**
     * Čeština ze seznamu jazyků („Angličtina<strong>*</strong>, Čeština, …<br>
     * <strong>*</strong>jazyky s plnou zvukovou podporou“) — bez hvězdičky jen
     * rozhraní a titulky, s ní i dabing. Null, když seznam chybí.
     */
    private static function czech(mixed $languages): ?CzechSupport
    {
        if (! is_string($languages) || trim($languages) === '') {
            return null;
        }

        $list = explode(self::FOOTNOTE_SEPARATOR, $languages)[0];
        foreach (explode(',', $list) as $language) {
            $text = strip_tags($language);
            $name = mb_strtolower(trim(str_replace(self::FULL_AUDIO_MARK, '', $text)));
            if (in_array($name, self::CZECH_NAMES, true)) {
                return str_contains($text, self::FULL_AUDIO_MARK) ? CzechSupport::Voice : CzechSupport::Text;
            }
        }

        return CzechSupport::None;
    }
}
