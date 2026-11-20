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

namespace ConsentManager\EventListener;

use ConsentManager\Model\ConsentRecordQuery;
use ConsentManager\Service\ConsentSettings;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Deletes the proofs older than their retention period, in the core's own
 * `maintenance:purge` run, and honours its dry run.
 */
final readonly class ConsentRecordPurgeListener
{
    public const BATCH_SIZE = 1000;

    public function __construct(
        private ConsentSettings $settings,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $days = $this->settings->retentionDays();
        if (0 === $days) {
            $event->addResult('<comment>Consent records:</comment> <info>kept (no retention period set)</info>');

            return;
        }

        $limit = new \DateTimeImmutable(\sprintf('-%d days', $days));
        $count = $event->isDryRun()
            ? ConsentRecordQuery::create()->olderThan($limit)->count()
            : $this->deleteInBatches($limit);

        $event->addResult(\sprintf(
            '<comment>Consent records (>%d days):</comment> <info>%d %s</info>',
            $days,
            $count,
            $event->isDryRun() ? 'to delete' : 'deleted',
        ));
    }

    /**
     * A shop that never purged may hold millions of proofs: deleting them in one
     * statement would lock the table for as long, under the visitors' new choices.
     */
    private function deleteInBatches(\DateTimeInterface $limit): int
    {
        $deleted = 0;
        do {
            $ids = ConsentRecordQuery::create()->olderThan($limit)->select(['Id'])->limit(self::BATCH_SIZE)->find()->getData();
            if ([] !== $ids) {
                $deleted += ConsentRecordQuery::create()->filterById($ids)->delete();
            }
        } while (\count($ids) === self::BATCH_SIZE);

        return $deleted;
    }
}
