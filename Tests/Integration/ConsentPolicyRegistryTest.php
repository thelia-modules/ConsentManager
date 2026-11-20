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
use ConsentManager\Service\ConsentPolicyRegistry;
use ConsentManager\Tests\ActivatesConsentManager;
use Thelia\Test\IntegrationTestCase;

final class ConsentPolicyRegistryTest extends IntegrationTestCase
{
    use ActivatesConsentManager;

    public function testAChangedTextIsANewVersionAndTheOldTextFindsItsOldVersion(): void
    {
        $registry = $this->getService(ConsentPolicyRegistry::class);
        $registry->reset();
        $before = (int) $registry->current()->getId();

        $category = ConsentCategoryQuery::create()->findOneByCode('analytics');
        $category->setLocale('en_US');
        $original = (string) $category->getTitle();
        $category->setTitle('Statistics')->save();
        $registry->reset();
        $changed = (int) $registry->current()->getId();

        $category->setTitle($original)->save();
        $registry->reset();
        $reverted = (int) $registry->current()->getId();

        self::assertNotSame($before, $changed);
        self::assertSame($before, $reverted);
    }
}
