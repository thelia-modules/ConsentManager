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

final readonly class CategoryView
{
    /**
     * @param list<ServiceView> $services
     */
    public function __construct(
        public string $code,
        public string $title,
        public string $description,
        public bool $required,
        public array $services = [],
    ) {
    }

    public function withServices(array $services): self
    {
        return new self($this->code, $this->title, $this->description, $this->required, $services);
    }
}
