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

namespace ConsentManager\Model;

use ConsentManager\Model\Base\ConsentRecordQuery as BaseConsentRecordQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class ConsentRecordQuery extends BaseConsentRecordQuery
{
    public function olderThan(\DateTimeInterface $limit): static
    {
        return $this->filterByCreatedAt($limit, Criteria::LESS_THAN);
    }

    public function latestOfVisitor(string $visitorId): ?ConsentRecord
    {
        return $this->filterByVisitorId($visitorId)
            ->orderByCreatedAt(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->findOne();
    }
}
