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

use ConsentManager\Contract\ConsentStateInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Whether the visitor of the current request accepted a category or a service.
 *
 * Only for responses that are never shared between visitors (an API call, a page
 * kept out of the shared cache). A cached page must emit its scripts through
 * {@see ScriptGate} and let the browser decide.
 */
class ConsentState implements ConsentStateInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ConsentPolicyRegistry $policies,
        private readonly ConsentCatalog $catalog,
    ) {
    }

    public function cookie(): ?ConsentCookie
    {
        $cookie = ConsentCookie::fromRequest($this->requestStack->getCurrentRequest());

        return null !== $cookie && $cookie->policyId === $this->policies->currentId() ? $cookie : null;
    }

    public function hasChosen(): bool
    {
        return null !== $this->cookie();
    }

    public function isCategoryGranted(string $categoryCode): bool
    {
        if (\in_array($categoryCode, $this->catalog->cachedRequiredCategoryCodes(), true)) {
            return true;
        }

        return \in_array($categoryCode, $this->cookie()->accepted ?? [], true);
    }

    public function isServiceGranted(string $serviceCode): bool
    {
        $category = $this->catalog->categoryOfService($serviceCode);

        return null !== $category && $this->isCategoryGranted($category);
    }
}
