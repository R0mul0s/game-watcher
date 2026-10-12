<?php

/**
 * Cena jako text pro e-mail a telefon (etapa 6, R26).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-12
 */

declare(strict_types=1);

use App\Domain\Pricing\Money;
use App\Domain\Pricing\MoneyFormatter;

it('formátuje česky — koruny bez haléřů, ostatní měny se dvěma místy', function (Money $money, string $text): void {
    expect(str_replace("\u{a0}", ' ', (new MoneyFormatter)->format($money)))->toBe($text);
})->with([
    'koruny' => [new Money(13181, 'CZK'), '132 Kč'],
    'tisíce korun' => [new Money(119827, 'CZK'), '1 198 Kč'],
    'eura' => [new Money(4918, 'EUR'), '49,18 €'],
    'eura s nulou' => [new Money(500, 'EUR'), '5,00 €'],
    'neznámá měna' => [new Money(123456, 'USD'), '1 234,56 USD'],
]);
