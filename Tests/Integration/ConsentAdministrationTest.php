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

use ConsentManager\Event\CollectConsentServicesEvent;
use ConsentManager\Event\ConsentManagerEvents;
use ConsentManager\Event\ConsentServiceDeclaration;
use ConsentManager\Exception\InvalidConsentChoiceException;
use ConsentManager\Model\ConsentCategoryQuery;
use ConsentManager\Service\ConsentAdministration;
use ConsentManager\Service\ConsentCache;
use ConsentManager\Service\ConsentCatalog;
use ConsentManager\Service\ConsentPolicyRegistry;
use ConsentManager\Tests\ActivatesConsentManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Test\IntegrationTestCase;

final class ConsentAdministrationTest extends IntegrationTestCase
{
    use ActivatesConsentManager;

    private ConsentAdministration $administration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetCachedConsentData();

        // A module of its own declaring one service: the catalog only sees this one.
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsentManagerEvents::COLLECT_SERVICES, static function (CollectConsentServicesEvent $event): void {
            $event->add(new ConsentServiceDeclaration(code: 'test_widget', title: 'Test widget', categoryCode: 'analytics'));
        });
        $this->administration = new ConsentAdministration(
            new ConsentCatalog($dispatcher, $this->getService('translator'), $this->getService(ConsentCache::class)),
            $this->getService(ConsentPolicyRegistry::class),
        );
    }

    public function testACategoryHoldingAModuleServiceCannotBeDeleted(): void
    {
        $this->expectException(InvalidConsentChoiceException::class);

        $this->administration->deleteCategory($this->categoryId('analytics'));
    }

    public function testAModuleServiceMovedAwayAndBackStillProtectsItsCategory(): void
    {
        $this->administration->assignService('test_widget', 'marketing');
        $this->administration->assignService('test_widget', 'analytics');

        $this->expectException(InvalidConsentChoiceException::class);

        $this->administration->deleteCategory($this->categoryId('analytics'));
    }

    public function testAModuleServiceMovedElsewhereFreesItsSuggestedCategory(): void
    {
        $this->administration->assignService('test_widget', 'marketing');

        $this->administration->deleteCategory($this->categoryId('analytics'));

        self::assertNull(ConsentCategoryQuery::create()->findOneByCode('analytics'));
    }

    private function categoryId(string $code): int
    {
        return (int) (ConsentCategoryQuery::create()->findOneByCode($code) ?? throw new \LogicException($code.' is a default category'))->getId();
    }
}
