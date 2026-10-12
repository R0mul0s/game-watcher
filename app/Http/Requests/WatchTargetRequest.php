<?php

/**
 * Cílová cena sledované hry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Pricing\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V měně zobrazení účtu (R14, R26); prázdná = upozornit na historické minimum.
 */
class WatchTargetRequest extends FormRequest
{
    /** Nejmenší cílová cena — nula by znamenala „jen zdarma“. */
    private const MIN_TARGET = 0.01;

    /** Částka s tečkou a nejvýš dvěma desetinnými místy (formulář posílá číslo). */
    private const AMOUNT_PATTERN = '/^\d+(\.\d{1,2})?$/';

    /**
     * Měnit smí každý přihlášený — jen vlastní seznam (řeší controller).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pravidla.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Číslo s nejvýš dvěma desetinnými místy — „1e3“ projde numeric, ale ne Money
            'target_price' => ['nullable', 'regex:'.self::AMOUNT_PATTERN, 'numeric', 'min:'.self::MIN_TARGET, 'max:'.config('games.alerts.max_target_price')],
        ];
    }

    /**
     * České hlášky.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_price.regex' => __('app.alerts.target_invalid'),
            'target_price.numeric' => __('app.alerts.target_invalid'),
            'target_price.min' => __('app.alerts.target_invalid'),
            'target_price.max' => __('app.alerts.target_invalid'),
        ];
    }

    /**
     * Cílová cena v dané měně; null = bez cíle. Z textu přes Money::fromDecimal,
     * bez floatu.
     */
    public function target(string $currency): ?Money
    {
        $value = trim($this->string('target_price')->toString());

        return $value === '' ? null : Money::fromDecimal($value, $currency);
    }
}
