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

use ConsentManager\ConsentManager;
use ConsentManager\Model\ConsentRecord;
use ConsentManager\Model\ConsentRecordQuery;
use ConsentManager\Service\ConsentPolicyRegistry;
use ConsentManager\Service\ConsentSettings;
use ConsentManager\Tests\ActivatesConsentManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Test\IntegrationTestCase;

final class ConsentRecordPurgeTest extends IntegrationTestCase
{
    use ActivatesConsentManager;

    private ?string $previousRetention = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetCachedConsentData();
        $this->previousRetention = ConsentManager::getConfigValue(ConsentSettings::RETENTION_DAYS);
    }

    protected function tearDown(): void
    {
        ConsentManager::setConfigValue(ConsentSettings::RETENTION_DAYS, $this->previousRetention ?? '0');
        parent::tearDown();
    }

    public function testProofsOlderThanTheRetentionPeriodGoAndADryRunOnlyCountsThem(): void
    {
        ConsentManager::setConfigValue(ConsentSettings::RETENTION_DAYS, '30');
        $old = $this->record('-31 days');
        $recent = $this->record('-29 days');

        $dryRun = $this->purge(true);
        self::assertNotNull(ConsentRecordQuery::create()->findPk($old->getId()), 'a dry run deletes nothing');
        self::assertStringContainsString('1 to delete', implode("\n", $dryRun->getResults()));

        $this->purge(false);
        self::assertNull(ConsentRecordQuery::create()->findPk($old->getId()));
        self::assertNotNull(ConsentRecordQuery::create()->findPk($recent->getId()));
    }

    public function testWithoutARetentionPeriodNothingIsDeleted(): void
    {
        ConsentManager::setConfigValue(ConsentSettings::RETENTION_DAYS, '0');
        $old = $this->record('-3000 days');

        $this->purge(false);

        self::assertNotNull(ConsentRecordQuery::create()->findPk($old->getId()));
    }

    private function record(string $age): ConsentRecord
    {
        $record = (new ConsentRecord())
            ->setVisitorId('0b3bbec7-abb3-4433-80c5-'.substr(md5($age), 0, 12))
            ->setPolicyId((int) $this->getService(ConsentPolicyRegistry::class)->current()->getId())
            ->setAccepted('["necessary"]')
            ->setRefused('[]')
            ->setCreatedAt(new \DateTimeImmutable($age));
        $record->save();

        return $record;
    }

    private function purge(bool $dryRun): MaintenancePurgeEvent
    {
        $event = new MaintenancePurgeEvent($dryRun);
        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

        return $event;
    }
}
