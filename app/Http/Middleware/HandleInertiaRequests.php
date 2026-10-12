<?php

/**
 * Sdílená data pro všechny Inertia stránky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Kořenová Blade šablona (resources/views/app.blade.php).
     *
     * @var string
     */
    protected $rootView = 'app';

    /** Položky hlavní navigace: název routy => klíč textu v app.ui.nav. */
    private const NAVIGATION = [
        'dashboard' => 'dashboard',
        'games.search' => 'add_game',
    ];

    /**
     * Sdílí s každou stránkou texty UI, jazyk, přihlášeného uživatele a navigaci.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'translations' => fn () => trans('app.ui'),
            'locale' => app()->getLocale(),
            'auth' => fn (): ?array => $user instanceof User ? [
                'name' => $user->name,
                'email' => $user->email,
                'logoutUrl' => route('logout', absolute: false),
            ] : null,
            'homeUrl' => fn (): string => route('dashboard', absolute: false),
            'timezone' => config('games.display_timezone'),
            // Přepínač měny v hlavičce (R14)
            'currency' => fn (): ?array => $user instanceof User ? [
                'current' => $user->displayCurrency(),
                'options' => config('games.prices.display_currencies'),
                'updateUrl' => route('settings.currency', absolute: false),
            ] : null,
            'navigation' => fn (): array => $user === null ? [] : array_map(
                fn (string $routeName, string $labelKey): array => [
                    'url' => route($routeName, absolute: false),
                    'label' => 'nav.'.$labelKey,
                    'active' => $request->routeIs($routeName),
                ],
                array_keys(self::NAVIGATION),
                self::NAVIGATION,
            ),
        ];
    }
}
