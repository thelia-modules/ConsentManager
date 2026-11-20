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

/**
 * @implements ProviderInterface<ConsentChoice>
 */
final readonly class ConsentChoiceProvider implements ProviderInterface
{
    public function __construct(
        private ConsentChoiceMapper $mapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ConsentChoice
    {
        $visitorId = (string) ($uriVariables['visitorId'] ?? '');
        if (!ConsentCookie::isVisitorId($visitorId)) {
            return null;
        }

        $record = ConsentRecordQuery::create()->latestOfVisitor($visitorId);

        return null === $record ? null : $this->mapper->toResource($record);
    }
}
