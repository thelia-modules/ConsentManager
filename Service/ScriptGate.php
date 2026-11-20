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

use ConsentManager\Contract\ScriptGateInterface;
use Psr\Log\LoggerInterface;

/**
 * Holds back a module's third-party markup until the visitor accepts the category of
 * its service. The HTML is the same for every visitor, so the page stays cacheable:
 *
 * - `<script>` becomes `type="text/plain"`, which the browser does not run, and the
 *   consent script turns it back into a running script once the category is accepted;
 * - `<iframe>` and `<img>` lose their `src`, given back on acceptance;
 * - `<noscript>` blocks are dropped: without JavaScript no choice can be made, so
 *   their fallback pixels would track a visitor who never agreed.
 *
 * Other modules reach it by its service id ({@see \ConsentManager\ConsentManager::SCRIPT_GATE_SERVICE}),
 * which only exists while this module is active.
 */
class ScriptGate implements ScriptGateInterface
{
    public function __construct(
        private readonly ConsentCatalog $catalog,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function gate(string $html, string $serviceCode): string
    {
        $category = $this->catalog->categoryOfService($serviceCode);
        if (null === $category) {
            // An undeclared service is held back for good rather than let through.
            $this->logger->warning(\sprintf('Consent manager: service "%s" is not declared, its markup stays blocked.', $serviceCode));
            $category = '__undeclared__';
        }

        return self::defer($html, $serviceCode, $category);
    }

    public static function defer(string $html, string $serviceCode, string $categoryCode): string
    {
        $marker = \sprintf(' data-consent-service="%s" data-consent-category="%s"', htmlspecialchars($serviceCode, \ENT_QUOTES), htmlspecialchars($categoryCode, \ENT_QUOTES));

        $html = (string) preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', '', $html);

        $html = (string) preg_replace_callback('#<script\b([^>]*)>#i', static function (array $match) use ($marker): string {
            $attributes = $match[1];
            $originalType = '';
            if (preg_match('#\stype\s*=\s*(["\'])(.*?)\1#i', $attributes, $type)) {
                $originalType = $type[2];
                $attributes = str_replace($type[0], '', $attributes);
            }
            if ('' !== $originalType && !\in_array(strtolower($originalType), ['text/javascript', 'application/javascript', 'module'], true)) {
                // Data blocks (JSON, templates) run nothing: left as they are.
                return $match[0];
            }
            $keep = '' !== $originalType ? \sprintf(' data-consent-type="%s"', htmlspecialchars($originalType, \ENT_QUOTES)) : '';

            return '<script type="text/plain"'.$keep.$marker.$attributes.'>';
        }, $html);

        return (string) preg_replace_callback('#<(iframe|img)\b([^>]*?)\ssrc\s*=\s*(["\'])(.*?)\3([^>]*)>#is', static fn (array $match): string => \sprintf('<%s%s data-consent-src=%s%s%s%s%s>', $match[1], $match[2], $match[3], $match[4], $match[3], $marker, $match[5]), $html);
    }
}
