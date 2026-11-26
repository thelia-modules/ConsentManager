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

namespace ConsentManager\Service;

use ConsentManager\Event\CollectConsentServicesEvent;
use ConsentManager\Event\ConsentManagerEvents;
use ConsentManager\Event\ConsentServiceDeclaration;
use ConsentManager\Model\ConsentCategory;
use ConsentManager\Model\ConsentCategoryQuery;
use ConsentManager\Model\ConsentService;
use ConsentManager\Model\ConsentServiceQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Lang;

/**
 * The categories the merchant set up, and every service the visitor is asked about:
 * those the active modules declare, with the category the merchant may have moved
 * them to, and those the merchant typed in.
 */
class ConsentCatalog
{
    /** @var array<string, ConsentServiceDeclaration>|null */
    private ?array $declarations = null;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        #[Autowire(service: 'translator')]
        private readonly TranslatorInterface $translator,
        private readonly ConsentCache $cache,
    ) {
    }

    /**
     * @return array<string, ConsentServiceDeclaration>
     */
    public function declarations(): array
    {
        if (null === $this->declarations) {
            $event = new CollectConsentServicesEvent();
            $this->dispatcher->dispatch($event, ConsentManagerEvents::COLLECT_SERVICES);
            $this->declarations = $event->all();
        }

        return $this->declarations;
    }

    /**
     * @return list<ConsentCategory>
     */
    public function categoryModels(): array
    {
        return iterator_to_array(
            ConsentCategoryQuery::create()->orderByPosition(Criteria::ASC)->orderById(Criteria::ASC)->find(),
            false,
        );
    }

    /**
     * @return list<string>
     */
    public function categoryCodes(): array
    {
        return array_map(static fn (ConsentCategory $category): string => $category->getCode(), $this->categoryModels());
    }

    /**
     * @return list<string>
     */
    public function requiredCategoryCodes(): array
    {
        return array_values(array_map(
            static fn (ConsentCategory $category): string => $category->getCode(),
            array_filter($this->categoryModels(), static fn (ConsentCategory $category): bool => 1 === (int) $category->getRequired()),
        ));
    }

    /**
     * @return list<CategoryView> each with the services it covers
     */
    public function categories(string $locale): array
    {
        $servicesByCategory = [];
        foreach ($this->services($locale) as $service) {
            $servicesByCategory[$service->categoryCode][] = $service;
        }

        $views = [];
        foreach ($this->categoryModels() as $category) {
            [$title, $description] = self::texts($category, $locale);
            $views[] = new CategoryView(
                $category->getCode(),
                '' !== $title ? $title : $category->getCode(),
                $description,
                1 === (int) $category->getRequired(),
                $servicesByCategory[$category->getCode()] ?? [],
            );
        }

        return $views;
    }

    /**
     * @return list<ServiceView>
     */
    public function services(string $locale): array
    {
        $categoryCodes = [];
        foreach ($this->categoryModels() as $category) {
            $categoryCodes[(int) $category->getId()] = $category->getCode();
        }

        /** @var array<string, ConsentService> $rows */
        $rows = [];
        foreach (ConsentServiceQuery::create()->find() as $row) {
            $rows[$row->getCode()] = $row;
        }

        $views = [];
        foreach ($this->declarations() as $code => $declaration) {
            $row = $rows[$code] ?? null;
            unset($rows[$code]);
            $views[] = new ServiceView(
                $code,
                $this->translate($declaration->title, $declaration->translationDomain, $locale),
                null !== $row ? ($categoryCodes[(int) $row->getCategoryId()] ?? $declaration->categoryCode) : $declaration->categoryCode,
                $declaration->provider,
                $declaration->cookies,
                $declaration->privacyUrl,
                $this->translate($declaration->description, $declaration->translationDomain, $locale),
                false,
                true,
                $this->translate($declaration->duration, $declaration->translationDomain, $locale),
            );
        }

        foreach ($rows as $code => $row) {
            if (1 !== (int) $row->getCustom()) {
                // The category chosen for a service whose module is no longer active.
                continue;
            }

            [$title, $description, $duration] = self::texts($row, $locale);
            $views[] = new ServiceView(
                $code,
                '' !== $title ? $title : $code,
                $categoryCodes[(int) $row->getCategoryId()] ?? '',
                (string) $row->getProvider(),
                self::splitCookies((string) $row->getCookies()),
                $row->getPrivacyUrl() ?: null,
                $description,
                true,
                false,
                $duration,
            );
        }

        usort($views, static fn (ServiceView $a, ServiceView $b): int => strnatcasecmp($a->title, $b->title));

        return $views;
    }

    /** Read on every page that gates markup: from the cache. */
    public function categoryOfService(string $serviceCode): ?string
    {
        return $this->cache->get('service_categories', $this->serviceCategoryMap(...))[$serviceCode] ?? null;
    }

    /**
     * @return list<string> read on every request that asks for a consent: from the cache
     */
    public function cachedRequiredCategoryCodes(): array
    {
        return $this->cache->get('required_categories', $this->requiredCategoryCodes(...));
    }

    /**
     * @return array<string, string> service code => category code, the merchant's choice first
     */
    public function serviceCategoryMap(): array
    {
        $map = [];
        foreach ($this->declarations() as $code => $declaration) {
            $map[$code] = $declaration->categoryCode;
        }

        $categoryCodes = [];
        foreach ($this->categoryModels() as $category) {
            $categoryCodes[(int) $category->getId()] = $category->getCode();
        }
        foreach (ConsentServiceQuery::create()->find() as $row) {
            if (isset($categoryCodes[(int) $row->getCategoryId()]) && (1 === (int) $row->getCustom() || isset($map[$row->getCode()]))) {
                $map[$row->getCode()] = $categoryCodes[(int) $row->getCategoryId()];
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function splitCookies(string $cookies): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', $cookies) ?: []), static fn (string $name): bool => '' !== $name)));
    }

    /**
     * Title and description in the asked language, else in the shop's default one: a
     * language activated after the categories were written still shows them.
     *
     * @return array{string, string, string} title, description, and duration for a service
     */
    private static function texts(ConsentCategory|ConsentService $model, string $locale): array
    {
        $model->setLocale($locale);
        if ('' === (string) $model->getTitle()) {
            $model->setLocale(Lang::getDefaultLanguage()->getLocale());
        }

        return [
            (string) $model->getTitle(),
            (string) $model->getDescription(),
            $model instanceof ConsentService ? (string) $model->getDuration() : '',
        ];
    }

    private function translate(string $text, ?string $domain, string $locale): string
    {
        if ('' === $text || null === $domain) {
            return $text;
        }

        return $this->translator->trans($text, [], $domain, $locale);
    }
}
