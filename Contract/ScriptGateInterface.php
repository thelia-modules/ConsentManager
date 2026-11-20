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
 * Holds a service's third-party markup back until the visitor accepts its category.
 *
 * Published under the service id `consent_manager.script_gate`. A project that uses a
 * commercial consent platform registers its own implementation under that id; the
 * modules that gate their markup keep working unchanged.
 */
interface ScriptGateInterface
{
    public function gate(string $html, string $serviceCode): string;
}
