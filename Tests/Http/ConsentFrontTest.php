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

use ConsentManager\Model\ConsentRecordQuery;
use ConsentManager\Service\ConsentPolicyRegistry;
use ConsentManager\Service\ConsentRecorder;
use ConsentManager\Tests\ActivatesConsentManager;
use Symfony\Component\BrowserKit\Cookie;
use Thelia\Test\WebIntegrationTestCase;

/**
 * What a visitor and a headless front see: the banner on a cacheable page, the
 * policy page, and the API that records and reads a choice.
 */
final class ConsentFrontTest extends WebIntegrationTestCase
{
    use ActivatesConsentManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetCachedConsentData();
    }

    public function testTheBannerIsInThePageForEveryoneAndNoOptionalCookieIsSetBeforeAChoice(): void
    {
        $this->assertPageRenders('/');

        $crawler = $this->client->getCrawler();
        self::assertSame(1, $crawler->filter('[data-testid="consent-manager-banner"][hidden]')->count(), 'hidden in the HTML, shown by the browser: the page carries no visitor state');
        self::assertSame(3, $crawler->filter('[data-testid="consent-manager-banner"] [data-consent-action]')->count());
        self::assertSame(1, $crawler->filter('[data-testid="consent-manager-open"]')->count(), 'the footer link to change one\'s mind');

        // What the page itself declares strictly necessary, as the banner script reads it.
        $configuration = json_decode($crawler->filter('#consent-manager-config')->text(), true);
        $necessary = array_merge(...array_column(array_filter($configuration['categories'], static fn (array $category): bool => $category['required']), 'cookies'));

        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if (null === $cookie->getValue() || $cookie->isCleared()) {
                continue;
            }
            self::assertTrue(
                \in_array($cookie->getName(), $necessary, true),
                \sprintf('"%s" is set before the visitor chose.', $cookie->getName()),
            );
        }
    }

    public function testThePolicyPageListsTheCategoriesAndTheDeclaredServices(): void
    {
        $this->assertPageRenders('/cookie-policy');

        $crawler = $this->client->getCrawler();
        foreach (['necessary', 'analytics', 'marketing'] as $code) {
            self::assertSame(1, $crawler->filter('[data-testid="consent-manager-policy-category-'.$code.'"]')->count());
        }
        self::assertStringContainsString('thelia_consent', $crawler->filter('[data-testid="consent-manager-policy-service-shop"]')->text());
    }

    public function testAChoiceIsRecordedAndReadBackThroughTheApi(): void
    {
        $this->client->request('POST', '/api/front/consent', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['accepted' => ['analytics'], 'refused' => ['marketing']]));

        self::assertSame(201, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
        $choice = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['necessary', 'analytics'], $choice['accepted']);
        self::assertSame(['marketing'], $choice['refused']);
        self::assertStringStartsWith($choice['policy'].'|'.$choice['visitorId'].'|', $choice['cookie']);
        self::assertSame(1, ConsentRecordQuery::create()->filterByVisitorId($choice['visitorId'])->count());

        $this->client->request('GET', '/api/front/consent', server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_THELIA_CONSENT' => $choice['cookie']]);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $read = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($choice['visitorId'], $read['visitorId']);
        self::assertTrue($read['current'], json_encode($read).' vs '.json_encode($choice));
    }

    public function testNobodyReadsOrExtendsTheChoiceOfSomebodyElse(): void
    {
        $victim = $this->getService(ConsentRecorder::class)->record(null, null, [], []);

        $this->client->request('GET', '/api/front/consent', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(404, $this->client->getResponse()->getStatusCode(), 'no consent cookie, no choice to read');

        $this->client->request('POST', '/api/front/consent', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['visitorId' => $victim->getVisitorId(), 'accepted' => ['analytics', 'marketing']]));
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        self::assertNotSame($victim->getVisitorId(), json_decode((string) $this->client->getResponse()->getContent(), true)['visitorId'], 'a visitor id named in the body is ignored');
        self::assertSame(1, ConsentRecordQuery::create()->filterByVisitorId($victim->getVisitorId())->count());
    }

    public function testTwoVisitorsWithDifferentChoicesReceiveTheSameBanner(): void
    {
        $policy = $this->getService(ConsentPolicyRegistry::class)->currentId();
        $banners = [];
        foreach (['necessary.analytics.marketing', 'necessary'] as $accepted) {
            $this->client->getCookieJar()->clear();
            $this->client->getCookieJar()->set(new Cookie('thelia_consent', $policy.'|0b3bbec7-abb3-4433-80c5-f94f2b10fa6d|'.$accepted));
            $this->assertPageRenders('/');
            $crawler = $this->client->getCrawler();
            $banners[] = $crawler->filter('#consent-manager')->outerHtml().$crawler->filter('#consent-manager-config')->outerHtml();
        }

        self::assertSame($banners[0], $banners[1], 'the choice is applied in the browser: a shared cache can serve the page to both');
    }

    public function testRefusingTheNecessaryCategoryIsRejectedByTheApi(): void
    {
        $this->client->request('POST', '/api/front/consent', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['accepted' => [], 'refused' => ['necessary']]));

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    public function testTheApiGivesTheSameCategoriesAsTheBanner(): void
    {
        $this->client->request('GET', '/api/front/consent-configuration?locale=fr_FR', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $configuration = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['necessary', 'analytics', 'marketing'], array_column($configuration['categories'], 'code'));
        self::assertTrue($configuration['categories'][0]['required']);
        self::assertSame('Strictement nécessaires', $configuration['categories'][0]['title']);
    }
}
