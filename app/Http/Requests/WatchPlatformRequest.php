<?php

/**
 * Změna platformy, na které uživatel hru hlídá.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Prázdná hodnota = všude (R24).
 */
class WatchPlatformRequest extends FormRequest
{
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
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['nullable', Rule::enum(Platform::class)],
        ];
    }

    /**
     * Hlídaná platforma; null = všude.
     */
    public function platform(): ?Platform
    {
        return Platform::tryFrom($this->string('platform')->toString());
    }
}
