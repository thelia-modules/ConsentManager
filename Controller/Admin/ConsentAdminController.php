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

namespace ConsentManager\Controller\Admin;

use ConsentManager\ConsentManager;
use ConsentManager\Exception\InvalidConsentChoiceException;
use ConsentManager\Model\ConsentCategoryQuery;
use ConsentManager\Model\ConsentPolicyQuery;
use ConsentManager\Model\ConsentRecordQuery;
use ConsentManager\Model\ConsentServiceQuery;
use ConsentManager\Service\ConsentAdministration;
use ConsentManager\Service\ConsentCatalog;
use ConsentManager\Service\ConsentCookie;
use ConsentManager\Service\ConsentPolicyRegistry;
use ConsentManager\Service\ConsentSettings;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

/**
 * The merchant's screens: banner and durations, categories, services, and the lookup
 * of a proof. A proof is shown, never edited.
 */
#[Route('/admin/module/ConsentManager', name: 'consentmanager.admin')]
class ConsentAdminController extends BaseAdminController
{
    private const TEMPLATES = '@ConsentManagerModule/backOffice/default-twig/ConsentManager/';
    private const FLASH_SUCCESS = 'consentmanager-success';
    private const FLASH_ERROR = 'consentmanager-error';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/settings', name: '.settings', methods: ['GET'])]
    public function settings(Environment $twig, ConsentSettings $settings, ConsentPolicyRegistry $policies): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], [ConsentManager::getModuleCode()], AccessManager::VIEW)) {
            return $response;
        }

        $banners = [];
        foreach (self::locales() as $locale => $title) {
            $banners[$locale] = ['language' => $title, 'title' => $settings->bannerTitle($locale), 'text' => $settings->bannerText($locale), 'intro' => $settings->policyIntro($locale)];
        }

        $current = $policies->current();

        return $this->page($twig, 'settings.html.twig', [
            'banners' => $banners,
            'lifetime_days' => $settings->lifetimeDays(),
            'retention_days' => $settings->retentionDays(),
            'policy' => ['id' => $current->getId(), 'created_at' => $current->getCreatedAt()],
            'record_count' => ConsentRecordQuery::create()->count(),
        ]);
    }

    #[Route('/settings', name: '.settings.save', methods: ['POST'])]
    public function saveSettings(Request $request, TokenProvider $tokens, ConsentSettings $settings, ConsentPolicyRegistry $policies): Response
    {
        return $this->mutate($request, $tokens, 'consentmanager.admin.settings', [], static function () use ($request, $settings, $policies): string {
            $lifetime = (int) $request->request->getString('lifetime_days');
            $retention = (int) $request->request->getString('retention_days');
            if ($lifetime < 1 || $lifetime > 395) {
                throw new InvalidConsentChoiceException('A choice is kept between 1 and 395 days (13 months).');
            }
            if ($retention < 0) {
                throw new InvalidConsentChoiceException('The retention period cannot be negative.');
            }
            // A proof purged while the choice it proves is still honoured would leave the shop
            // acting on a consent it can no longer prove.
            if (0 !== $retention && $retention < $lifetime) {
                throw new InvalidConsentChoiceException('A proof is kept at least as long as the choice it proves.');
            }

            $banners = $request->request->all('banner');
            foreach (array_keys(self::locales()) as $locale) {
                $settings->saveBanner($locale, trim((string) ($banners[$locale]['title'] ?? '')), trim((string) ($banners[$locale]['text'] ?? '')));
                $settings->savePolicyIntro($locale, trim((string) ($banners[$locale]['intro'] ?? '')));
            }
            $settings->saveDurations($lifetime, $retention);
            $policies->reset();

            return 'Settings saved.';
        });
    }

    #[Route('/categories', name: '.categories', methods: ['GET'])]
    public function categories(Environment $twig, ConsentCatalog $catalog, Request $request): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], [ConsentManager::getModuleCode()], AccessManager::VIEW)) {
            return $response;
        }

        return $this->page($twig, 'categories.html.twig', [
            'categories' => $catalog->categories($request->getLocale()),
            'ids' => array_combine(
                array_map(static fn ($category): string => $category->getCode(), $catalog->categoryModels()),
                array_map(static fn ($category): int => (int) $category->getId(), $catalog->categoryModels()),
            ) ?: [],
        ]);
    }

    #[Route('/categories/new', name: '.category.new', methods: ['GET'])]
    #[Route('/categories/{id}', name: '.category.edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function editCategory(Environment $twig, ?int $id = null): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], [ConsentManager::getModuleCode()], AccessManager::VIEW)) {
            return $response;
        }

        $category = null === $id ? null : ConsentCategoryQuery::create()->findPk($id);
        if (null !== $id && null === $category) {
            return $this->redirectTo('consentmanager.admin.categories');
        }

        $texts = [];
        foreach (self::locales() as $locale => $language) {
            $category?->setLocale($locale);
            $texts[$locale] = ['language' => $language, 'title' => (string) $category?->getTitle(), 'description' => (string) $category?->getDescription()];
        }

        return $this->page($twig, 'category-edit.html.twig', [
            'id' => $id,
            'code' => $category?->getCode() ?? '',
            'required' => 1 === (int) $category?->getRequired(),
            'texts' => $texts,
        ]);
    }

    #[Route('/categories/save', name: '.category.save', methods: ['POST'])]
    public function saveCategory(Request $request, TokenProvider $tokens, ConsentAdministration $administration): Response
    {
        // An empty field on a new category: getInt() would refuse it with a 400.
        $id = (int) $request->request->getString('id') ?: null;

        return $this->mutate($request, $tokens, 'consentmanager.admin.categories', [], static function () use ($request, $administration, $id): string {
            $administration->saveCategory($id, trim($request->request->getString('code')), $request->request->getBoolean('required'), self::texts($request));

            return 'Category saved.';
        });
    }

    #[Route('/categories/{id}/move/{direction}', name: '.category.move', requirements: ['id' => '\d+', 'direction' => 'up|down'], methods: ['POST'])]
    public function moveCategory(int $id, string $direction, Request $request, TokenProvider $tokens, ConsentAdministration $administration): Response
    {
        return $this->mutate($request, $tokens, 'consentmanager.admin.categories', [], static function () use ($administration, $id, $direction): string {
            $administration->moveCategory($id, 'up' === $direction ? -1 : 1);

            return 'Order saved.';
        });
    }

    #[Route('/categories/{id}/delete', name: '.category.delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteCategory(int $id, Request $request, TokenProvider $tokens, ConsentAdministration $administration): Response
    {
        return $this->mutate($request, $tokens, 'consentmanager.admin.categories', [], static function () use ($administration, $id): string {
            $administration->deleteCategory($id);

            return 'Category deleted.';
        }, AccessManager::DELETE);
    }

    #[Route('/services', name: '.services', methods: ['GET'])]
    public function services(Environment $twig, ConsentCatalog $catalog, Request $request): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], [ConsentManager::getModuleCode()], AccessManager::VIEW)) {
            return $response;
        }

        return $this->page($twig, 'services.html.twig', [
            'services' => $catalog->services($request->getLocale()),
            'categories' => $catalog->categories($request->getLocale()),
        ]);
    }

    #[Route('/services/{code}/category', name: '.service.assign', requirements: ['code' => '[a-z0-9_]{1,64}'], methods: ['POST'])]
    public function assignService(string $code, Request $request, TokenProvider $tokens, ConsentAdministration $administration): Response
    {
        return $this->mutate($request, $tokens, 'consentmanager.admin.services', [], static function () use ($administration, $code, $request): string {
            $administration->assignService($code, $request->request->getString('category'));

            return 'Service moved.';
        });
    }

    #[Route('/services/new', name: '.service.new', methods: ['GET'])]
    #[Route('/services/{code}', name: '.service.edit', requirements: ['code' => '[a-z0-9_]{1,64}'], methods: ['GET'])]
    public function editService(Environment $twig, ConsentCatalog $catalog, Request $request, ?string $code = null): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], [ConsentManager::getModuleCode()], AccessManager::VIEW)) {
            return $response;
        }

        $service = null === $code ? null : ConsentServiceQuery::create()->filterByCustom(1)->findOneByCode($code);
        if (null !== $code && null === $service) {
            return $this->redirectTo('consentmanager.admin.services');
        }

        $texts = [];
        foreach (self::locales() as $locale => $language) {
            $service?->setLocale($locale);
            $texts[$locale] = ['language' => $language, 'title' => (string) $service?->getTitle(), 'description' => (string) $service?->getDescription(), 'duration' => (string) $service?->getDuration()];
        }

        return $this->page($twig, 'service-edit.html.twig', [
            'code' => $service?->getCode() ?? '',
            'existing' => null !== $service,
            'category' => null === $service ? '' : (string) ConsentCategoryQuery::create()->findPk($service->getCategoryId())?->getCode(),
            'provider' => (string) $service?->getProvider(),
            'cookies' => (string) $service?->getCookies(),
            'privacy_url' => (string) $service?->getPrivacyUrl(),
            'texts' => $texts,
            'categories' => $catalog->categories($request->getLocale()),
        ]);
    }

    #[Route('/services/save', name: '.service.save', methods: ['POST'])]
    public function saveService(Request $request, TokenProvider $tokens, ConsentAdministration $administration): Response
    {
        $existing = $request->request->getString('existing') ?: null;

        return $this->mutate($request, $tokens, 'consentmanager.admin.services', [], static function () use ($request, $administration, $existing): string {
            $administration->saveCustomService(
                $existing,
                trim($request->request->getString('code')),
                $request->request->getString('category'),
                $request->request->getString('provider'),
                $request->request->getString('cookies'),
                $request->request->getString('privacy_url'),
                self::texts($request),
            );

            return 'Service saved.';
        });
    }

    #[Route('/services/{code}/delete', name: '.service.delete', requirements: ['code' => '[a-z0-9_]{1,64}'], methods: ['POST'])]
    public function deleteService(string $code, Request $request, TokenProvider $tokens, ConsentAdministration $administration): Response
    {
        return $this->mutate($request, $tokens, 'consentmanager.admin.services', [], static function () use ($administration, $code): string {
            $administration->deleteCustomService($code);

            return 'Service deleted.';
        }, AccessManager::DELETE);
    }

    #[Route('/records', name: '.records', methods: ['GET'])]
    public function records(Environment $twig, Request $request): Response
    {
        if (null !== $response = $this->checkAuth([ConsentManager::PROOFS_RESOURCE], [], AccessManager::VIEW)) {
            return $response;
        }

        $visitorId = strtolower(trim($request->query->getString('visitor')));
        $records = [];
        if (ConsentCookie::isVisitorId($visitorId)) {
            foreach (ConsentRecordQuery::create()->filterByVisitorId($visitorId)->orderByCreatedAt(Criteria::DESC)->orderById(Criteria::DESC)->find() as $record) {
                $records[] = [
                    'id' => $record->getId(),
                    'created_at' => $record->getCreatedAt(),
                    'policy' => $record->getPolicyId(),
                    'accepted' => $record->getAcceptedCategories(),
                    'refused' => $record->getRefusedCategories(),
                ];
            }
        }

        $policyId = (int) $request->query->getString('policy');
        $policy = $policyId > 0 ? ConsentPolicyQuery::create()->findPk($policyId) : null;

        return $this->page($twig, 'records.html.twig', [
            'visitor' => $visitorId,
            'searched' => '' !== $visitorId,
            'valid' => ConsentCookie::isVisitorId($visitorId),
            'records' => $records,
            'policy' => null === $policy ? null : [
                'id' => $policy->getId(),
                'created_at' => $policy->getCreatedAt(),
                'snapshot' => json_encode(json_decode($policy->getSnapshot(), true), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function redirectTo(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate($route, $parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function page(Environment $twig, string $template, array $parameters): Response
    {
        return new Response($twig->render(self::TEMPLATES.$template, $parameters));
    }

    /**
     * @param array<string, mixed> $routeParameters
     * @param callable(): string   $change          returns the success message
     */
    private function mutate(Request $request, TokenProvider $tokens, string $route, array $routeParameters, callable $change, string $access = AccessManager::UPDATE): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], [ConsentManager::getModuleCode()], $access)) {
            return $response;
        }

        $session = $request->getSession();
        \assert($session instanceof FlashBagAwareSessionInterface);
        $flashBag = $session->getFlashBag();

        try {
            $tokens->checkRequestToken($request);
        } catch (\Throwable) {
            $flashBag->add(self::FLASH_ERROR, $this->getTranslator()->trans('Your session expired, please try again.', [], ConsentManager::BO_DOMAIN));

            return $this->redirectTo($route, $routeParameters);
        }

        try {
            $flashBag->add(self::FLASH_SUCCESS, $this->getTranslator()->trans($change(), [], ConsentManager::BO_DOMAIN));
        } catch (InvalidConsentChoiceException $exception) {
            $flashBag->add(self::FLASH_ERROR, $this->getTranslator()->trans($exception->getMessage(), [], ConsentManager::BO_DOMAIN));
        }

        return $this->redirectTo($route, $routeParameters);
    }

    /**
     * @return array<string, string> locale => language title
     */
    private static function locales(): array
    {
        $locales = [];
        foreach (LangQuery::create()->filterByActive(true)->orderByPosition()->find() as $lang) {
            $locales[$lang->getLocale()] = (string) $lang->getTitle();
        }

        return $locales;
    }

    /**
     * @return array<string, array{title: string, description: string, duration: string}>
     */
    private static function texts(Request $request): array
    {
        $texts = [];
        $posted = $request->request->all('texts');
        foreach (array_keys(self::locales()) as $locale) {
            $texts[$locale] = [
                'title' => (string) ($posted[$locale]['title'] ?? ''),
                'description' => (string) ($posted[$locale]['description'] ?? ''),
                'duration' => (string) ($posted[$locale]['duration'] ?? ''),
            ];
        }

        return $texts;
    }
}
