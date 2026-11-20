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

use ConsentManager\Service\ConsentCookie;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsentCookieTest extends TestCase
{
    public function testAValueReadsBackAsItWasWritten(): void
    {
        $cookie = new ConsentCookie(4, '0b3bbec7-abb3-4433-80c5-f94f2b10fa6d', ['necessary', 'analytics']);

        self::assertSame('4|0b3bbec7-abb3-4433-80c5-f94f2b10fa6d|necessary.analytics', $cookie->format());
        self::assertEquals($cookie, ConsentCookie::parse($cookie->format()));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function forgedValues(): iterable
    {
        yield 'empty' => [''];
        yield 'missing' => [null];
        yield 'two parts' => ['1|0b3bbec7-abb3-4433-80c5-f94f2b10fa6d'];
        yield 'policy not a number' => ['x|0b3bbec7-abb3-4433-80c5-f94f2b10fa6d|necessary'];
        yield 'visitor not a random uuid' => ['1|0b3bbec7-abb3-1433-80c5-f94f2b10fa6d|necessary'];
        yield 'category with markup' => ['1|0b3bbec7-abb3-4433-80c5-f94f2b10fa6d|necessary.<b>'];
    }

    #[DataProvider('forgedValues')]
    public function testAForgedValueMeansNoChoice(?string $value): void
    {
        self::assertNull(ConsentCookie::parse($value));
    }
}
