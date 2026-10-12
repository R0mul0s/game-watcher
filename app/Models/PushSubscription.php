<?php

/**
 * Odběr upozornění Web Push jednoho zařízení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zařízení patří přihlášenému uživateli (R13) — převzato z Počasí, tam jsou
 * odběry anonymní. Adresa odběru je delší, než dovolí unikátní index, proto se
 * hledá a hlídá podle hashe. Klíče prohlížeče jsou tajemství pro šifrování
 * zprávy — do odpovědí nejdou.
 *
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PushSubscription extends Model
{
    private const HASH_ALGORITHM = 'sha256';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'public_key',
        'auth_token',
    ];

    /**
     * Hash adresy odběru pro vyhledání a unikátní index.
     */
    public static function hashEndpoint(string $endpoint): string
    {
        return hash(self::HASH_ALGORITHM, $endpoint);
    }

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Účet, kterému zařízení patří.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
