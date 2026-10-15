<?php

/**
 * Druh záznamu v centru upozornění.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-15
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Hodnota sloupce notifications.type (R53) — krátký kód místo názvu třídy,
 * podle něj stránka volí ikonu a text.
 */
enum NotificationKind: string
{
    /** Upozornění na jednu hru — cena, vydání, Game Pass, bundle (PriceWatch, ReleaseWatch, OfferWatch). */
    case GameAlert = 'game_alert';

    /** Týdenní souhrn slev (R36). */
    case WeeklyDigest = 'weekly_digest';

    /** Výpadek hlídaných zdrojů cen, nebo jejich obnova (R29). */
    case Sources = 'sources';
}
