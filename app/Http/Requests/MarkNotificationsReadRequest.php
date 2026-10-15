<?php

/**
 * Označení záznamů centra upozornění jako přečtených.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ID záznamů (UUID), které stránka centra ukazuje jako nepřečtené (R53).
 */
class MarkNotificationsReadRequest extends FormRequest
{
    /**
     * Smí každý přihlášený — jen vlastní záznamy (řeší controller).
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
            'ids' => ['required', 'array', 'max:'.config('games.notifications.max_mark_read')],
            'ids.*' => ['required', 'uuid'],
        ];
    }

    /**
     * Vlastní hláška — aplikace nemá přeložené validační texty Laravelu.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.*' => __('app.validation.notification_ids'),
            'ids' => __('app.validation.notification_ids'),
        ];
    }

    /**
     * ID záznamů.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values(array_map(strval(...), (array) $this->validated('ids')));
    }
}
