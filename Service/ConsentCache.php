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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * What every page needs (banner texts, categories, services, current policy version),
 * computed once and read from the application cache afterwards: the banner is paid by
 * all the traffic, so displaying it reads nothing from the database.
 *
 * A change in the back-office bumps the generation, which retires every entry at once.
 * Activating or deactivating a module clears the application cache, so the services it
 * declares show up without waiting; the lifetime is only a safety net.
 */
class ConsentCache
{
    private const GENERATION_KEY = 'consentmanager.generation';
    private const LIFETIME = 3600;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(): T $compute
     *
     * @return T
     */
    public function get(string $key, callable $compute): mixed
    {
        return $this->cache->get(
            \sprintf('consentmanager.%d.%s', $this->generation(), preg_replace('/[^A-Za-z0-9_.]/', '_', $key)),
            static function (ItemInterface $item) use ($compute): mixed {
                $item->expiresAfter(self::LIFETIME);

                return $compute();
            },
        );
    }

    public function invalidate(): void
    {
        $next = $this->generation() + 1;
        $this->cache->delete(self::GENERATION_KEY);
        $this->cache->get(self::GENERATION_KEY, static fn (): int => $next);
    }

    private function generation(): int
    {
        return (int) $this->cache->get(self::GENERATION_KEY, static fn (): int => 0);
    }
}
