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

use ConsentManager\Api\Resource\ConsentChoice;
use ConsentManager\Model\ConsentRecord;

final readonly class ConsentChoiceMapper
{
    public function __construct(
        private ConsentPolicyRegistry $policies,
        private ConsentSettings $settings,
    ) {
    }

    public function toResource(ConsentRecord $record): ConsentChoice
    {
        $choice = new ConsentChoice();
        $choice->visitorId = $record->getVisitorId();
        $choice->policy = (int) $record->getPolicyId();
        $choice->accepted = $record->getAcceptedCategories();
        $choice->refused = $record->getRefusedCategories();
        $createdAt = $record->getCreatedAt();
        $choice->recordedAt = null === $createdAt ? null : \DateTimeImmutable::createFromInterface($createdAt);
        $choice->current = $choice->policy === (int) $this->policies->current()->getId();
        $choice->cookie = (new ConsentCookie($choice->policy, $choice->visitorId, $choice->accepted))->format();
        $choice->maxAge = $this->settings->lifetimeDays() * 86400;

        return $choice;
    }
}
