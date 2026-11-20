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

use Symfony\Component\HttpFoundation\Request;

/**
 * The strictly necessary cookie that remembers a choice: `<policy>|<visitor>|<a.b.c>`.
 *
 * It is written in the browser, so that pages served from a shared cache never carry it,
 * and read here only by code that runs outside such a cache.
 */
final readonly class ConsentCookie
{
    public const NAME = 'thelia_consent';

    /** A headless front on another domain sends the cookie value in this header instead. */
    public const HEADER = 'X-Thelia-Consent';

    private const VISITOR_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * @param list<string> $accepted
     */
    public function __construct(
        public int $policyId,
        public string $visitorId,
        public array $accepted,
    ) {
    }

    public static function parse(?string $value): ?self
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $parts = explode('|', $value);
        if (3 !== \count($parts) || !ctype_digit($parts[0]) || !self::isVisitorId($parts[1])) {
            return null;
        }

        $accepted = '' === $parts[2] ? [] : explode('.', $parts[2]);
        foreach ($accepted as $code) {
            if (1 !== preg_match('/^[a-z0-9_]{1,64}$/', $code)) {
                return null;
            }
        }

        return new self((int) $parts[0], $parts[1], $accepted);
    }

    /** The choice the request carries, whatever policy it was made under. */
    public static function fromRequest(?Request $request): ?self
    {
        if (null === $request) {
            return null;
        }

        return self::parse($request->headers->get(self::HEADER) ?? $request->cookies->get(self::NAME));
    }

    public static function isVisitorId(string $value): bool
    {
        return 1 === preg_match(self::VISITOR_PATTERN, $value);
    }

    public function format(): string
    {
        return $this->policyId.'|'.$this->visitorId.'|'.implode('.', $this->accepted);
    }
}
