<?php

/**
 * Zařízení určené adresou odběru (odhlášení, zkušební upozornění).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PushEndpoint;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Jen adresa — odběr se hledá podle jejího hashe mezi zařízeními účtu.
 */
class PushEndpointRequest extends FormRequest
{
    /**
     * Smí každý přihlášený — jen vlastní zařízení (řeší controller).
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
            'endpoint' => ['required', 'string', 'max:'.PushEndpoint::MAX_LENGTH],
        ];
    }
}
