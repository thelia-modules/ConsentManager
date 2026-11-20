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

/**
 * What a module tells the visitor about one of its services.
 *
 * The category is only a suggestion: the merchant can move the service to another
 * category from the back-office. Title, description and duration (how long its cookies
 * last, "13 months") are translated in the given
 * domain when one is set, so a module ships them in English with its own catalog.
 */
final readonly class ConsentServiceDeclaration
{
    /**
     * @param list<string> $cookies names of the cookies the service sets; a trailing * matches a prefix (`_ga*`)
     */
    public function __construct(
        public string $code,
        public string $title,
        public string $categoryCode,
        public string $provider = '',
        public array $cookies = [],
        public ?string $privacyUrl = null,
        public string $description = '',
        public ?string $translationDomain = null,
        public string $duration = '',
    ) {
        if (1 !== preg_match('/^[a-z0-9_]{1,64}$/', $code)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid consent service code: lowercase letters, digits and underscores only.', $code));
        }
    }
}
