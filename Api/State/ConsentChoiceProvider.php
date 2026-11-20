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
use ConsentManager\Api\Resource\ConsentChoice;
use ConsentManager\Model\ConsentRecordQuery;
use ConsentManager\Service\ConsentChoiceMapper;
use ConsentManager\Service\ConsentCookie;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<ConsentChoice>
 */
final readonly class ConsentChoiceProvider implements ProviderInterface
{
    public function __construct(
        private ConsentChoiceMapper $mapper,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ConsentChoice
    {
        $cookie = ConsentCookie::fromRequest($this->requestStack->getCurrentRequest());
        if (null === $cookie) {
            return null;
        }

        $record = ConsentRecordQuery::create()->latestOfVisitor($cookie->visitorId);

        return null === $record ? null : $this->mapper->toResource($record);
    }
}
