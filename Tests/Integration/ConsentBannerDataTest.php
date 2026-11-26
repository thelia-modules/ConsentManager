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

use ConsentManager\Model\ConsentCategoryQuery;
use ConsentManager\Service\ConsentAdministration;
use ConsentManager\Service\ConsentBannerData;
use ConsentManager\Service\ConsentCatalog;
use ConsentManager\Tests\ActivatesConsentManager;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Test\IntegrationTestCase;

/**
 * The banner is shown to all the traffic: once computed, displaying it reads nothing
 * from the database, and a change in the back-office shows on the next page.
 */
final class ConsentBannerDataTest extends IntegrationTestCase
{
    use ActivatesConsentManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetCachedConsentData();
    }

    public function testOnceComputedTheBannerAndTheGateReadNothingFromTheDatabase(): void
    {
        $bannerData = $this->getService(ConsentBannerData::class);
        $catalog = $this->getService(ConsentCatalog::class);
        $bannerData->build('en_US');
        $catalog->categoryOfService('shop');
        $catalog->cachedRequiredCategoryCodes();

        $connection = Propel::getServiceContainer()->getWriteConnection('TheliaMain');
        \assert($connection instanceof ConnectionWrapper);
        $connection->useDebug(true);
        $before = $connection->getQueryCount();

        $bannerData->build('en_US');
        $catalog->categoryOfService('shop');
        $catalog->cachedRequiredCategoryCodes();

        self::assertSame($before, $connection->getQueryCount());
    }

    public function testABackOfficeChangeShowsOnTheNextPage(): void
    {
        $bannerData = $this->getService(ConsentBannerData::class);
        $before = $bannerData->build('en_US');

        $analytics = ConsentCategoryQuery::create()->findOneByCode('analytics');
        $this->getService(ConsentAdministration::class)->saveCategory((int) $analytics->getId(), 'analytics', false, ['en_US' => ['title' => 'Statistics', 'description' => '']]);

        $after = $bannerData->build('en_US');
        self::assertSame('Statistics', $after['categories'][1]['title']);
        self::assertNotSame($before['policy'], $after['policy']);
    }
}
