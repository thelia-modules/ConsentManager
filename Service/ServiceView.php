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

final readonly class ServiceView
{
    /**
     * @param list<string> $cookies
     */
    public function __construct(
        public string $code,
        public string $title,
        public string $categoryCode,
        public string $provider,
        public array $cookies,
        public ?string $privacyUrl,
        public string $description,
        public bool $custom,
        public bool $declared,
        public string $duration = '',
    ) {
    }
}
