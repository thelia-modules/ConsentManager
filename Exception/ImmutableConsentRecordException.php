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

namespace ConsentManager\Exception;

final class ImmutableConsentRecordException extends \LogicException
{
    public function __construct(int $recordId)
    {
        parent::__construct(\sprintf('Consent record %d is a proof and cannot be modified.', $recordId));
    }
}
