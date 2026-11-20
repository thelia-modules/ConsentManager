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

namespace ConsentManager\Contract;

/**
 * Whether the visitor of the current request accepted a category or a service.
 *
 * Published under the service id `consent_manager.consent_state`. Only for responses
 * never shared between visitors: a cached page must gate its markup instead.
 */
interface ConsentStateInterface
{
    public function hasChosen(): bool;

    public function isCategoryGranted(string $categoryCode): bool;

    public function isServiceGranted(string $serviceCode): bool;
}
