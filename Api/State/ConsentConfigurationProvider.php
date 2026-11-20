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

namespace ConsentManager\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ConsentManager\Api\Resource\ConsentConfiguration;
use ConsentManager\Service\ConsentBannerData;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * @implements ProviderInterface<ConsentConfiguration>
 */
final readonly class ConsentConfigurationProvider implements ProviderInterface
{
    public function __construct(
        private ConsentBannerData $bannerData,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ConsentConfiguration
    {
        $data = $this->bannerData->build($this->locale());

        $configuration = new ConsentConfiguration();
        $configuration->policy = $data['policy'];
        $configuration->cookieName = $data['cookieName'];
        $configuration->lifetimeDays = $data['lifetimeDays'];
        $configuration->categories = $data['categories'];

        return $configuration;
    }

    /** The `locale` query parameter when it names an active language, else the shop's default one. */
    private function locale(): string
    {
        $asked = (string) $this->requestStack->getCurrentRequest()?->query->get('locale', '');
        if ('' !== $asked && null !== LangQuery::create()->filterByActive(true)->findOneByLocale($asked)) {
            return $asked;
        }

        return Lang::getDefaultLanguage()->getLocale();
    }
}
