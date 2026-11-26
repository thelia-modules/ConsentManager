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

namespace ConsentManager\Event;

final class ConsentManagerEvents
{
    /**
     * Dispatched with a {@see CollectConsentServicesEvent}: every module that loads a
     * third-party script or sets a cookie the visitor may refuse declares it here.
     *
     * Listen to the string, not to this constant, from a module that must keep working
     * when this one is not installed.
     */
    public const COLLECT_SERVICES = 'consent_manager.services.collect';
}
