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

use ConsentManager\Exception\InvalidConsentChoiceException;
use ConsentManager\Model\ConsentCategory;
use ConsentManager\Model\ConsentCategoryQuery;
use ConsentManager\Model\ConsentService;
use ConsentManager\Model\ConsentServiceQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * What the back-office changes. Every change alters the policy the visitors see: the
 * next page computes a new version and the banner asks again.
 */
class ConsentAdministration
{
    private const CODE_PATTERN = '/^[a-z0-9_]{1,64}$/';

    public function __construct(
        private readonly ConsentCatalog $catalog,
        private readonly ConsentPolicyRegistry $policies,
    ) {
    }

    /**
     * @param array<string, array{title: string, description: string}> $texts by locale
     */
    public function saveCategory(?int $id, string $code, bool $required, array $texts): ConsentCategory
    {
        if (null === $id) {
            self::assertCode($code);
            if (null !== ConsentCategoryQuery::create()->findOneByCode($code)) {
                throw new InvalidConsentChoiceException(\sprintf('A category with the code "%s" already exists.', $code));
            }
            $category = (new ConsentCategory())
                ->setCode($code)
                ->setPosition(1 + (int) ConsentCategoryQuery::create()->withColumn('MAX(position)', 'maxPosition')->select('maxPosition')->findOne());
        } else {
            $category = ConsentCategoryQuery::create()->findPk($id) ?? throw new InvalidConsentChoiceException('No such category.');
        }

        $category->setRequired($required ? 1 : 0);
        foreach ($texts as $locale => $text) {
            $category->setLocale($locale)->setTitle(trim($text['title']))->setDescription(trim($text['description']));
        }
        $category->save();
        $this->policies->reset();

        return $category;
    }

    public function moveCategory(int $id, int $offset): void
    {
        $categories = $this->catalog->categoryModels();
        $index = null;
        foreach ($categories as $position => $category) {
            if ((int) $category->getId() === $id) {
                $index = $position;
            }
        }
        $target = null === $index ? null : $index + $offset;
        if (null === $target || !isset($categories[$target])) {
            return;
        }

        [$categories[$index], $categories[$target]] = [$categories[$target], $categories[$index]];
        foreach ($categories as $position => $category) {
            $category->setPosition($position + 1)->save();
        }
        $this->policies->reset();
    }

    public function deleteCategory(int $id): void
    {
        $category = ConsentCategoryQuery::create()->findPk($id) ?? throw new InvalidConsentChoiceException('No such category.');
        if (1 === (int) $category->getRequired()) {
            throw new InvalidConsentChoiceException('The strictly necessary category cannot be deleted.');
        }
        if (ConsentServiceQuery::create()->filterByCategoryId($id)->filterByCustom(1)->count() > 0) {
            throw new InvalidConsentChoiceException('Move or delete the services of this category first.');
        }
        foreach ($this->catalog->declarations() as $declaration) {
            if ($declaration->categoryCode === $category->getCode() && null === ConsentServiceQuery::create()->findOneByCode($declaration->code)) {
                throw new InvalidConsentChoiceException('A module places one of its services in this category: move it to another one first.');
            }
        }

        $category->delete();
        $this->policies->reset();
    }

    public function assignService(string $code, string $categoryCode): void
    {
        $category = ConsentCategoryQuery::create()->findOneByCode($categoryCode) ?? throw new InvalidConsentChoiceException('No such category.');
        $service = ConsentServiceQuery::create()->findOneByCode($code);
        if (null === $service) {
            if (!isset($this->catalog->declarations()[$code])) {
                throw new InvalidConsentChoiceException('No such service.');
            }
            $service = (new ConsentService())->setCode($code)->setCustom(0);
        }

        $service->setCategoryId((int) $category->getId())->save();
        $this->policies->reset();
    }

    /**
     * @param array<string, array{title: string, description: string, duration: string}> $texts by locale
     */
    public function saveCustomService(?string $existingCode, string $code, string $categoryCode, string $provider, string $cookies, string $privacyUrl, array $texts): ConsentService
    {
        $category = ConsentCategoryQuery::create()->findOneByCode($categoryCode) ?? throw new InvalidConsentChoiceException('No such category.');
        if ('' !== $privacyUrl && !preg_match('#^https?://#i', $privacyUrl)) {
            throw new InvalidConsentChoiceException('The privacy policy address must start with http:// or https://.');
        }

        if (null === $existingCode) {
            self::assertCode($code);
            if (null !== ConsentServiceQuery::create()->findOneByCode($code) || isset($this->catalog->declarations()[$code])) {
                throw new InvalidConsentChoiceException(\sprintf('A service with the code "%s" already exists.', $code));
            }
            $service = (new ConsentService())->setCode($code)->setCustom(1);
        } else {
            $service = ConsentServiceQuery::create()->filterByCustom(1)->findOneByCode($existingCode) ?? throw new InvalidConsentChoiceException('No such service.');
        }

        $service
            ->setCategoryId((int) $category->getId())
            ->setProvider(trim($provider))
            ->setCookies(implode(', ', ConsentCatalog::splitCookies($cookies)))
            ->setPrivacyUrl(trim($privacyUrl));
        foreach ($texts as $locale => $text) {
            $service->setLocale($locale)->setTitle(trim($text['title']))->setDescription(trim($text['description']))->setDuration(trim($text['duration']));
        }
        $service->save();
        $this->policies->reset();

        return $service;
    }

    public function deleteCustomService(string $code): void
    {
        ConsentServiceQuery::create()->filterByCustom(1)->filterByCode($code)->delete();
        $this->policies->reset();
    }

    /**
     * @return list<ConsentCategory>
     */
    public function categories(): array
    {
        return iterator_to_array(ConsentCategoryQuery::create()->orderByPosition(Criteria::ASC)->find(), false);
    }

    private static function assertCode(string $code): void
    {
        if (1 !== preg_match(self::CODE_PATTERN, $code)) {
            throw new InvalidConsentChoiceException('A code is made of lowercase letters, digits and underscores, 64 at most.');
        }
    }
}
