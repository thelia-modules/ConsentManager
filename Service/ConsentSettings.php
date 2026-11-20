<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ConsentManager\Service;

use ConsentManager\ConsentManager;

/**
 * The merchant's settings. Banner texts are per language; an empty text falls back to
 * the module's own wording, translated.
 */
class ConsentSettings
{
    public const BANNER_TITLE = 'banner_title';
    public const BANNER_TEXT = 'banner_text';
    public const LIFETIME_DAYS = 'consent_lifetime_days';
    public const RETENTION_DAYS = 'record_retention_days';

    /** Six months: how long a choice is kept before the banner asks again. */
    public const DEFAULT_LIFETIME_DAYS = 180;

    /** Off until the merchant sets a period: a proof deleted too early cannot be produced. */
    public const DEFAULT_RETENTION_DAYS = 0;

    public function bannerTitle(string $locale): string
    {
        return (string) ConsentManager::getConfigValue(self::BANNER_TITLE, '', $locale);
    }

    public function bannerText(string $locale): string
    {
        return (string) ConsentManager::getConfigValue(self::BANNER_TEXT, '', $locale);
    }

    public function lifetimeDays(): int
    {
        $days = (int) ConsentManager::getConfigValue(self::LIFETIME_DAYS, (string) self::DEFAULT_LIFETIME_DAYS);

        return $days > 0 ? $days : self::DEFAULT_LIFETIME_DAYS;
    }

    public function retentionDays(): int
    {
        return max(0, (int) ConsentManager::getConfigValue(self::RETENTION_DAYS, (string) self::DEFAULT_RETENTION_DAYS));
    }

    public function saveBanner(string $locale, string $title, string $text): void
    {
        ConsentManager::setConfigValue(self::BANNER_TITLE, $title, $locale);
        ConsentManager::setConfigValue(self::BANNER_TEXT, $text, $locale);
    }

    public function saveDurations(int $lifetimeDays, int $retentionDays): void
    {
        ConsentManager::setConfigValue(self::LIFETIME_DAYS, (string) $lifetimeDays);
        ConsentManager::setConfigValue(self::RETENTION_DAYS, (string) $retentionDays);
    }
}
