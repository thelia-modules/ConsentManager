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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Everything the banner shows, in one language. The theme renders it into the page and
 * the API returns it to a headless front, so both ask the same questions.
 */
final readonly class ConsentBannerData
{
    public function __construct(
        private ConsentCatalog $catalog,
        private ConsentPolicyRegistry $policies,
        private ConsentSettings $settings,
        #[Autowire(service: 'translator')]
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{policy: int, cookieName: string, lifetimeDays: int, title: string, text: string, categories: list<array{code: string, title: string, description: string, required: bool, services: list<array{code: string, title: string, provider: string, cookies: list<string>, privacyUrl: ?string, description: string}>}>}
     */
    public function build(string $locale): array
    {
        $categories = [];
        foreach ($this->catalog->categories($locale) as $category) {
            $categories[] = [
                'code' => $category->code,
                'title' => $category->title,
                'description' => $category->description,
                'required' => $category->required,
                'services' => array_map(static fn (ServiceView $service): array => [
                    'code' => $service->code,
                    'title' => $service->title,
                    'provider' => $service->provider,
                    'cookies' => $service->cookies,
                    'privacyUrl' => $service->privacyUrl,
                    'description' => $service->description,
                ], $category->services),
            ];
        }

        return [
            'policy' => (int) $this->policies->current()->getId(),
            'cookieName' => ConsentCookie::NAME,
            'lifetimeDays' => $this->settings->lifetimeDays(),
            'title' => $this->settings->bannerTitle($locale) ?: $this->translator->trans('We use cookies', [], ConsentManager::FO_DOMAIN, $locale),
            'text' => $this->settings->bannerText($locale) ?: $this->translator->trans('Some cookies are needed for the shop to work. With your consent, we also use others to measure the audience and show you relevant advertising. You can change your mind at any time from the "Cookie settings" link at the bottom of every page.', [], ConsentManager::FO_DOMAIN, $locale),
            'categories' => $categories,
        ];
    }
}
