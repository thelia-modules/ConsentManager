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

namespace ConsentManager\Controller\Front;

use ConsentManager\Service\ConsentBannerData;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;

/**
 * The cookie policy, built from the categories and the services actually declared,
 * so that its text cannot drift away from what the shop loads.
 */
final class CookiePolicyController extends BaseFrontController
{
    #[Route('/cookie-policy', name: 'consentmanager.cookie_policy', methods: ['GET'])]
    public function show(Request $request, ConsentBannerData $bannerData): Response
    {
        return $this->render('cookie-policy.html.twig', [
            'consent' => $bannerData->build($request->getLocale()),
        ]);
    }
}
