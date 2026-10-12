<?php

/**
 * Peněžní částka v setinách.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Pricing\Money;

it('přečte desetinný zápis bez převodu přes float', function (string $amount, int $cents): void {
    expect(Money::fromDecimal($amount, 'eur')->cents)->toBe($cents);
})->with([
    ['49.18', 4918],
    ['5.4', 540],
    ['1419', 141900],
    ['1419.00', 141900],
    ['0.29', 29],
    ['-3.05', -305],
]);

it('měnu sjednotí na velká písmena', function (): void {
    expect(Money::fromDecimal('1', 'eur')->currency)->toBe('EUR');
});

it('odmítne nečíselnou částku', function (string $amount): void {
    Money::fromDecimal($amount, 'EUR');
})->with(['', 'abc', '1,50', '1.2.3'])->throws(InvalidArgumentException::class);

it('do databáze zapíše dvě desetinná místa', function (int $cents, string $decimal): void {
    expect((new Money($cents, 'EUR'))->toDecimal())->toBe($decimal);
})->with([
    [4918, '49.18'],
    [540, '5.40'],
    [5, '0.05'],
    [-305, '-3.05'],
]);

it('pro stránku vrátí částku a měnu', function (): void {
    expect((new Money(2496, 'EUR'))->toArray())->toBe(['amount' => 24.96, 'currency' => 'EUR']);
});
