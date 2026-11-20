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

use ConsentManager\Model\ConsentPolicy;
use ConsentManager\Model\ConsentPolicyQuery;
use ConsentManager\Model\ConsentServiceQuery;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\LangQuery;

/**
 * The version of what the visitor is shown: banner texts, categories and services, in
 * every language. Any change gives a new version, a proof names the version its visitor
 * saw, and a choice made under an older version no longer counts: the banner asks again.
 *
 * Versions are found by the hash of their content, so going back to an earlier wording
 * finds its earlier version instead of minting a new one.
 */
class ConsentPolicyRegistry
{
    private ?ConsentPolicy $current = null;

    public function __construct(
        private readonly ConsentCatalog $catalog,
        private readonly ConsentSettings $settings,
        private readonly ConsentCache $cache,
    ) {
    }

    /** The version every page shows: from the cache, computed only after a change. */
    public function currentId(): int
    {
        return $this->cache->get('policy_id', fn (): int => (int) $this->current()->getId());
    }

    public function current(): ConsentPolicy
    {
        if (null !== $this->current) {
            return $this->current;
        }

        $snapshot = json_encode($this->snapshot(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $snapshot);

        $policy = ConsentPolicyQuery::create()->findOneBySnapshotHash($hash);
        if (null === $policy) {
            try {
                $policy = (new ConsentPolicy())
                    ->setSnapshotHash($hash)
                    ->setSnapshot($snapshot)
                    ->setCreatedAt(new \DateTimeImmutable());
                $policy->save();
            } catch (PropelException $exception) {
                // Two first visits after a change race on the unique hash: the other one won.
                $policy = ConsentPolicyQuery::create()->findOneBySnapshotHash($hash) ?? throw $exception;
            }
        }

        return $this->current = $policy;
    }

    /** After the back-office changed something: the next page computes the new version. */
    public function reset(): void
    {
        $this->current = null;
        $this->cache->invalidate();
    }

    public function exists(int $policyId): bool
    {
        return null !== ConsentPolicyQuery::create()->findPk($policyId);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $locales = [];
        foreach (LangQuery::create()->filterByActive(true)->find() as $lang) {
            $locales[] = $lang->getLocale();
        }
        sort($locales);

        $banner = [];
        foreach ($locales as $locale) {
            $banner[$locale] = [$this->settings->bannerTitle($locale), $this->settings->bannerText($locale), $this->settings->policyIntro($locale)];
        }

        $categories = [];
        foreach ($this->catalog->categoryModels() as $category) {
            $texts = [];
            foreach ($locales as $locale) {
                $category->setLocale($locale);
                $texts[$locale] = [(string) $category->getTitle(), (string) $category->getDescription()];
            }
            $categories[] = ['code' => $category->getCode(), 'required' => (int) $category->getRequired(), 'texts' => $texts];
        }

        $services = [];
        foreach ($this->catalog->declarations() as $code => $declaration) {
            $services[$code] = [
                'title' => $declaration->title,
                'provider' => $declaration->provider,
                'cookies' => $declaration->cookies,
                'privacy' => $declaration->privacyUrl,
                'description' => $declaration->description,
                'duration' => $declaration->duration,
            ];
        }
        foreach (ConsentServiceQuery::create()->find() as $row) {
            $texts = [];
            foreach ($locales as $locale) {
                $row->setLocale($locale);
                $texts[$locale] = [(string) $row->getTitle(), (string) $row->getDescription(), (string) $row->getDuration()];
            }
            $services[$row->getCode()] = array_merge($services[$row->getCode()] ?? [], [
                'category' => $this->catalog->categoryOfService($row->getCode()),
                'custom' => (int) $row->getCustom(),
                'customProvider' => (string) $row->getProvider(),
                'customCookies' => (string) $row->getCookies(),
                'customPrivacy' => (string) $row->getPrivacyUrl(),
                'texts' => 1 === (int) $row->getCustom() ? $texts : null,
            ]);
        }
        foreach ($this->catalog->declarations() as $code => $declaration) {
            $services[$code]['category'] ??= $declaration->categoryCode;
        }
        ksort($services);

        return ['banner' => $banner, 'categories' => $categories, 'services' => $services];
    }
}
