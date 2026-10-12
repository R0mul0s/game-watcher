<?php

/**
 * Požadavek cronu z WebAdminu hostingu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ověří token z query stringu. Neplatný nebo nenastavený token odpoví 404 —
 * URL navenek neexistuje, dokud se nezná token (stejně jako v projektu Počasí).
 */
class CronRequest extends FormRequest
{
    /**
     * Smí jen volání se správným tokenem; prázdný token v konfiguraci URL vypíná.
     */
    public function authorize(): bool
    {
        $expected = config('games.cron.token');
        $given = $this->query('token');

        return is_string($expected) && $expected !== '' && is_string($given) && hash_equals($expected, $given);
    }

    /**
     * Žádná další validační pravidla.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Místo 403 odpoví 404, aby URL neprozrazovala, že existuje.
     */
    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException;
    }
}
