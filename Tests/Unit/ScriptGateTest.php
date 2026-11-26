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

use ConsentManager\Service\ScriptGate;
use PHPUnit\Framework\TestCase;

final class ScriptGateTest extends TestCase
{
    public function testAnInlineScriptNoLongerRuns(): void
    {
        $html = ScriptGate::defer('<script>track();</script>', 'gtm', 'analytics');

        self::assertSame('<script type="text/plain" data-consent-service="gtm" data-consent-category="analytics">track();</script>', $html);
    }

    public function testAnExternalScriptKeepsItsSourceAndItsType(): void
    {
        $html = ScriptGate::defer('<script type="module" src="https://cdn.example/t.js" async></script>', 'gtm', 'analytics');

        self::assertStringContainsString('type="text/plain"', $html);
        self::assertStringContainsString('data-consent-type="module"', $html);
        self::assertStringContainsString('src="https://cdn.example/t.js"', $html);
        self::assertSame(1, substr_count($html, ' type="'), 'one type attribute for the browser, the original kept aside');
    }

    public function testADataBlockIsLeftAlone(): void
    {
        $json = '<script type="application/ld+json">{"a":1}</script>';

        self::assertSame($json, ScriptGate::defer($json, 'gtm', 'analytics'));
    }

    public function testFramesAndPixelsLoseTheirSourceAndNoscriptFallbacksAreDropped(): void
    {
        $html = ScriptGate::defer('<iframe src="https://video.example/1" width="10"></iframe><noscript><img src="https://px.example/p.gif"></noscript><img src="https://px.example/q.gif" alt="">', 'video', 'marketing');

        self::assertStringNotContainsString('<noscript', $html);
        self::assertStringNotContainsString('p.gif', $html);
        self::assertStringNotContainsString(' src=', $html);
        self::assertStringContainsString('<iframe data-consent-src="https://video.example/1" data-consent-service="video" data-consent-category="marketing" width="10">', $html);
        self::assertStringContainsString('<img data-consent-src="https://px.example/q.gif"', $html);
    }
}
