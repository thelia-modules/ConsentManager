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

use Symfony\Contracts\EventDispatcher\Event;

final class CollectConsentServicesEvent extends Event
{
    /** @var array<string, ConsentServiceDeclaration> */
    private array $declarations = [];

    public function add(ConsentServiceDeclaration $declaration): self
    {
        $this->declarations[$declaration->code] = $declaration;

        return $this;
    }

    /**
     * @return array<string, ConsentServiceDeclaration>
     */
    public function all(): array
    {
        return $this->declarations;
    }
}
