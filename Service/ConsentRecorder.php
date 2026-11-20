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

use ConsentManager\Exception\InvalidConsentChoiceException;
use ConsentManager\Model\ConsentRecord;
use Symfony\Component\Uid\Uuid;

/**
 * Writes the proof of a choice.
 *
 * The visitor is named by a random identifier and nothing else: no address, no
 * account, no fingerprint. A category the visitor did not mention counts as refused;
 * a strictly necessary one is always accepted, and refusing it is an error.
 */
class ConsentRecorder
{
    public function __construct(
        private readonly ConsentCatalog $catalog,
        private readonly ConsentPolicyRegistry $policies,
    ) {
    }

    /**
     * @param list<string> $accepted
     * @param list<string> $refused
     */
    public function record(?string $visitorId, ?int $policyId, array $accepted, array $refused): ConsentRecord
    {
        if (null !== $visitorId && '' !== $visitorId && !ConsentCookie::isVisitorId($visitorId)) {
            throw new InvalidConsentChoiceException('The visitor identifier is not one this module issued.');
        }

        $policyId ??= (int) $this->policies->current()->getId();
        if (!$this->policies->exists($policyId)) {
            throw new InvalidConsentChoiceException(\sprintf('No consent policy version %d.', $policyId));
        }

        $known = $this->catalog->categoryCodes();
        $required = $this->catalog->requiredCategoryCodes();

        $unknown = array_diff([...$accepted, ...$refused], $known);
        if ([] !== $unknown) {
            throw new InvalidConsentChoiceException(\sprintf('Unknown consent categories: %s.', implode(', ', $unknown)));
        }

        $refusedRequired = array_intersect($refused, $required);
        if ([] !== $refusedRequired) {
            throw new InvalidConsentChoiceException(\sprintf('Strictly necessary categories cannot be refused: %s.', implode(', ', $refusedRequired)));
        }

        $both = array_intersect($accepted, $refused);
        if ([] !== $both) {
            throw new InvalidConsentChoiceException(\sprintf('Categories both accepted and refused: %s.', implode(', ', $both)));
        }

        $acceptedCodes = array_values(array_filter($known, static fn (string $code): bool => \in_array($code, $accepted, true) || \in_array($code, $required, true)));
        $refusedCodes = array_values(array_diff($known, $acceptedCodes));

        $record = (new ConsentRecord())
            ->setVisitorId($visitorId ?: Uuid::v4()->toRfc4122())
            ->setPolicyId($policyId)
            ->setAccepted(json_encode($acceptedCodes, \JSON_THROW_ON_ERROR))
            ->setRefused(json_encode($refusedCodes, \JSON_THROW_ON_ERROR))
            ->setCreatedAt(new \DateTimeImmutable());
        $record->save();

        return $record;
    }
}
