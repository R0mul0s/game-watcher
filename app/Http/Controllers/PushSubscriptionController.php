<?php

/**
 * Upozornění na zařízení — odběr, odhlášení a zkušební zpráva.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Alerts\DeliveryResult;
use App\Domain\Alerts\PushSender;
use App\Http\Requests\PushEndpointRequest;
use App\Http\Requests\PushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * JSON pro resources/js/lib/push.js (vzor z Počasí, R13). Odběr patří
 * přihlášenému účtu — zařízení, které předtím patřilo jinému účtu (jiné
 * přihlášení ve stejném prohlížeči), se převede na aktuální.
 */
class PushSubscriptionController extends Controller
{
    /**
     * Uloží nebo obnoví odběr zařízení.
     */
    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $subscription = $request->subscription();
        $hash = PushSubscription::hashEndpoint($subscription['endpoint']);

        $known = $user->pushSubscriptions()->where('endpoint_hash', $hash)->exists();
        if (! $known && $user->pushSubscriptions()->count() >= (int) config('games.push.max_subscriptions_per_user')) {
            return response()->json(['message' => __('app.alerts.device_limit')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $hash],
            [...$subscription, 'user_id' => $user->id],
        );

        return response()->json(['subscribed' => true]);
    }

    /**
     * Odhlásí zařízení (prohlížeč ruší odběr sám).
     */
    public function destroy(PushEndpointRequest $request): JsonResponse
    {
        $this->user($request)->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($request->string('endpoint')->value()))
            ->delete();

        return response()->json(['subscribed' => false]);
    }

    /**
     * Zkušební upozornění na zařízení — ověří celý řetězec až k telefonu.
     */
    public function test(PushEndpointRequest $request, PushSender $sender): JsonResponse
    {
        $subscription = $this->user($request)->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($request->string('endpoint')->value()))
            ->first();
        if ($subscription === null) {
            return response()->json(['result' => DeliveryResult::Expired->value], Response::HTTP_NOT_FOUND);
        }

        $result = $sender->deliver($subscription, [
            'title' => __('app.alerts.test_title'),
            'body' => __('app.alerts.test_body'),
            'url' => route('settings.show', absolute: false),
            'tag' => 'test',
        ], 'test');

        return response()->json(['result' => $result->value]);
    }

    /**
     * Přihlášený uživatel — routy jsou za middlewarem `auth`.
     */
    private function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : abort(401);
    }
}
