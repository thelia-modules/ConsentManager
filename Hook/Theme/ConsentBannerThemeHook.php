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

namespace ConsentManager\Hook\Theme;

use ConsentManager\Service\ConsentBannerData;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * The banner and the footer links. Their markup is the same for every visitor: the
 * browser reads the consent cookie and decides what to show, so a page served from a
 * shared cache never carries anyone's choice.
 */
final readonly class ConsentBannerThemeHook implements ThemeHookInterface
{
    public function __construct(
        private Environment $twig,
        private ConsentBannerData $bannerData,
        private RequestStack $requestStack,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return \in_array($hookName, ['layout.body.bottom', 'layout.footer.links'], true);
    }

    public function render(string $hookName, array $parameters): string
    {
        return match ($hookName) {
            'layout.body.bottom' => $this->twig->render('@ConsentManagerModule/theme-hook/banner.html.twig', [
                'consent' => $this->bannerData->build($this->requestStack->getCurrentRequest()?->getLocale() ?? 'en_US'),
            ]),
            'layout.footer.links' => $this->twig->render('@ConsentManagerModule/theme-hook/footer-links.html.twig'),
            default => '',
        };
    }
}
