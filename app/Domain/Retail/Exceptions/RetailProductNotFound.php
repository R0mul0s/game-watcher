<?php

/**
 * Odkaz nevede na produkt podporovaného obchodu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Domain\Retail\Exceptions;

use RuntimeException;

/**
 * Jiný hostitel, stránka neexistuje, nebo na ní není produkt (kategorie,
 * výpis) — při připojování chyba formuláře, ne výpadek (R52).
 */
final class RetailProductNotFound extends RuntimeException {}
