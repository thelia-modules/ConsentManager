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

namespace ConsentManager\Tests\Integration;

use ConsentManager\Exception\ImmutableConsentRecordException;
use ConsentManager\Exception\InvalidConsentChoiceException;
use ConsentManager\Model\ConsentRecordQuery;
use ConsentManager\Service\ConsentRecorder;
use ConsentManager\Tests\ActivatesConsentManager;
use Thelia\Test\IntegrationTestCase;

final class ConsentRecorderTest extends IntegrationTestCase
{
    use ActivatesConsentManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetCachedConsentData();
    }

    private function recorder(): ConsentRecorder
    {
        return $this->getService(ConsentRecorder::class);
    }

    public function testACategoryNotMentionedIsRefusedAndTheNecessaryOneIsAlwaysAccepted(): void
    {
        $record = $this->recorder()->record(null, null, ['analytics'], []);

        self::assertSame(['necessary', 'analytics'], $record->getAcceptedCategories());
        self::assertSame(['marketing'], $record->getRefusedCategories());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $record->getVisitorId());
    }

    public function testRefusingTheNecessaryCategoryIsRejected(): void
    {
        $this->expectException(InvalidConsentChoiceException::class);

        $this->recorder()->record(null, null, [], ['necessary']);
    }

    public function testAnUnknownCategoryIsRejected(): void
    {
        $this->expectException(InvalidConsentChoiceException::class);

        $this->recorder()->record(null, null, ['everything'], []);
    }

    public function testAVisitorChangingTheirMindKeepsTheirIdentifierAndBothProofsRemain(): void
    {
        $first = $this->recorder()->record(null, null, ['analytics', 'marketing'], []);
        $second = $this->recorder()->record($first->getVisitorId(), null, [], ['analytics', 'marketing']);

        self::assertSame($first->getVisitorId(), $second->getVisitorId());
        self::assertSame(2, ConsentRecordQuery::create()->filterByVisitorId($first->getVisitorId())->count());
        self::assertSame(['necessary'], ConsentRecordQuery::create()->latestOfVisitor($first->getVisitorId())?->getAcceptedCategories());
    }

    public function testAProofCannotBeModified(): void
    {
        $record = $this->recorder()->record(null, null, ['analytics'], []);
        $record->setAccepted('["necessary","analytics","marketing"]');

        $this->expectException(ImmutableConsentRecordException::class);

        $record->save();
    }
}
