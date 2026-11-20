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

use ConsentManager\ConsentManager;
use ConsentManager\Event\CollectConsentServicesEvent;
use ConsentManager\Event\ConsentManagerEvents;
use ConsentManager\Event\ConsentServiceDeclaration;
use ConsentManager\Service\ConsentCookie;
use ConsentManager\Service\DefaultCategories;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\ConfigQuery;

/**
 * The cookies the shop itself sets: the session, the cart, "remember me" and the
 * memory of the consent choice. All strictly necessary, listed so the policy is complete.
 */
final readonly class ShopCookiesDeclaration
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(event: ConsentManagerEvents::COLLECT_SERVICES)]
    public function declare(CollectConsentServicesEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();
        $sessionName = null !== $request && $request->hasSession() ? $request->getSession()->getName() : 'PHPSESSID';

        $event->add(new ConsentServiceDeclaration(
            code: 'shop',
            title: 'This shop',
            categoryCode: DefaultCategories::NECESSARY,
            cookies: [
                $sessionName,
                ConfigQuery::read('cart.cookie_name', 'thelia_cart'),
                ConfigQuery::read('customer_remember_me_cookie_name', 'crmcn'),
                ConsentCookie::NAME,
            ],
            description: 'Keeps your session, your cart, your login when you ask to be remembered, and this consent choice.',
            translationDomain: ConsentManager::FO_DOMAIN,
            duration: 'From the end of the visit to one year',
        ));
    }
}
