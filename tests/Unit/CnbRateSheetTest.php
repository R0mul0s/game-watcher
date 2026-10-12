<?php

/**
 * Rozbor kurzovního lístku ČNB.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

use App\Domain\Currency\CnbRateSheet;
use App\Domain\Currency\Exceptions\ExchangeRatesUnavailable;

it('přečte den vyhlášení a kurzy za jednu jednotku', function (): void {
    $sheet = CnbRateSheet::parse((string) file_get_contents(fixture('cnb/denni_kurz.txt')));

    expect($sheet->day->toDateString())->toBe('2026-10-09')
        ->and($sheet->rates['EUR'])->toBe('24.365000')
        ->and($sheet->rates['USD'])->toBe('21.745000')
        // Peso je na lístku za 100 jednotek
        ->and($sheet->rates['PHP'])->toBe('0.346150')
        ->and($sheet->rates)->toHaveCount(30);
});

it('odmítne text bez hlavičky lístku', function (): void {
    CnbRateSheet::parse("<html>Údržba</html>\nEMU|euro|1|EUR|24,365");
})->throws(ExchangeRatesUnavailable::class);

it('odmítne lístek bez kurzů', function (): void {
    CnbRateSheet::parse("09.10.2026 #195\nzemě|měna|množství|kód|kurz\n");
})->throws(ExchangeRatesUnavailable::class);
