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

namespace ConsentManager\Tests\Http;

use ConsentManager\Model\ConsentCategoryQuery;
use ConsentManager\Service\ConsentRecorder;
use ConsentManager\Tests\ActivatesConsentManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Admin;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

final class ConsentBackOfficeTest extends WebIntegrationTestCase
{
    use ActivatesConsentManager;

    private ?AdminSessionInjector $injector = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        $this->loginAs((new FixtureFactory($this->getPropelConnection()))->admin());
    }

    protected function tearDown(): void
    {
        $this->injector?->clear();
        parent::tearDown();
    }

    public function testEveryScreenRenders(): void
    {
        foreach (['settings', 'categories', 'categories/new', 'services', 'services/new', 'records'] as $screen) {
            $this->assertPageRenders('/admin/module/ConsentManager/'.$screen);
        }

        $this->assertPageRenders('/admin/module/ConsentManager/services');
        self::assertSame(1, $this->client->getCrawler()->filter('[data-testid="consentmanager-service-shop"]')->count());
    }

    public function testTheMerchantFindsAProofFromItsIdentifierAndCannotEditIt(): void
    {
        $record = $this->getService(ConsentRecorder::class)->record(null, null, ['analytics'], []);

        $this->assertPageRenders('/admin/module/ConsentManager/records?visitor='.$record->getVisitorId());

        $table = $this->client->getCrawler()->filter('[data-testid="consentmanager-records"]');
        self::assertSame(1, $table->count());
        self::assertStringContainsString('necessary, analytics', $table->text());
        self::assertStringContainsString('marketing', $table->text());
        self::assertSame(0, $table->filter('form, input, button')->count(), 'a proof is shown, never edited');
    }

    public function testAddingACategoryGoesThroughTheTokenedForm(): void
    {
        $this->assertPageRenders('/admin/module/ConsentManager/categories/new');
        $form = $this->client->getCrawler()->filter('[data-testid="consentmanager-category-form"]')->form();

        $this->client->submit($form, ['code' => 'social', 'texts[en_US][title]' => 'Social networks']);

        self::assertTrue($this->client->getResponse()->isRedirect(), $this->client->getResponse()->getStatusCode().' '.substr(strip_tags((string) $this->client->getResponse()->getContent()), 0, 600));
        self::assertNotNull(ConsentCategoryQuery::create()->findOneByCode('social'));
    }

    public function testAFormWithoutItsTokenChangesNothing(): void
    {
        $this->client->request('POST', '/admin/module/ConsentManager/categories/save', ['code' => 'forged', 'texts' => []]);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertNull(ConsentCategoryQuery::create()->findOneByCode('forged'));
    }

    public function testTheNecessaryCategoryCannotBeDeleted(): void
    {
        $necessary = ConsentCategoryQuery::create()->findOneByCode('necessary');
        $this->assertPageRenders('/admin/module/ConsentManager/categories');
        $token = $this->client->getCrawler()->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/module/ConsentManager/categories/'.$necessary->getId().'/delete', ['_token' => $token]);

        self::assertNotNull(ConsentCategoryQuery::create()->findPk($necessary->getId()));
    }

    private function loginAs(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector?->setAdmin($admin);
    }
}
