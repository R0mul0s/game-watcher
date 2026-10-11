<?php

/**
 * Základní třída testů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testy nepotřebují sestavené assety — Vite se vypíná. Žádný test nesmí
     * volat skutečné API ani obchod — jen přes Http::fake() se zachycenými
     * odpověďmi (docs/CODING_GUIDELINES.md, sekce 3 — Testy).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }
}
