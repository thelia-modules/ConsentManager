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

namespace ConsentManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ComposerConstraintsTest extends TestCase
{
    /**
     * The permanent "Cookie settings" link lives in the `layout.footer.links` hook point,
     * which Flexy declares from 1.5.0: on an older Flexy the visitor could not change their mind.
     */
    public function testTheModuleCannotBeInstalledWithAFlexyWithoutTheFooterLinksHookPoint(): void
    {
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('<1.5.0', $composer['conflict']['thelia/flexy'] ?? null);
    }
}
