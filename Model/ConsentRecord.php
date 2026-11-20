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

use ConsentManager\Exception\ImmutableConsentRecordException;
use ConsentManager\Model\Base\ConsentRecord as BaseConsentRecord;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * A proof of consent is written once. Nothing in the module updates one, and the
 * model refuses it too, so that no later code path can rewrite what a visitor chose.
 * The retention purge deletes rows through a query, which never loads them.
 */
class ConsentRecord extends BaseConsentRecord
{
    public function preUpdate(?ConnectionInterface $con = null): bool
    {
        throw new ImmutableConsentRecordException((int) $this->getId());
    }

    /**
     * @return list<string>
     */
    public function getAcceptedCategories(): array
    {
        return self::decodeCodes($this->getAccepted());
    }

    /**
     * @return list<string>
     */
    public function getRefusedCategories(): array
    {
        return self::decodeCodes($this->getRefused());
    }

    /**
     * @return list<string>
     */
    private static function decodeCodes(?string $json): array
    {
        $codes = json_decode((string) $json, true);

        return \is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }
}
