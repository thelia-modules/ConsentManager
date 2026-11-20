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

namespace ConsentManager\Tests;

use Symfony\Component\Process\Process;
use Thelia\Model\ModuleQuery;

/**
 * The module ships inactive, and an inactive module has no route, no table and no
 * service: activate it in the test database and rebuild the test container once,
 * with the database settings of the test kernel.
 */
trait ActivatesConsentManager
{
    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        $container = static::getContainer();
        $active = $container->has('consent_manager.script_gate');
        $environment = [];
        foreach (['host', 'port', 'name', 'user', 'password'] as $setting) {
            $environment['DATABASE_'.strtoupper($setting)] = (string) $container->getParameter('thelia.database_'.$setting);
        }
        self::ensureKernelShutdown();

        if ($active) {
            return;
        }

        $console = [\PHP_BINARY, $kernel->getProjectDir().'/bin/console'];
        $options = ['--env='.$kernel->getEnvironment(), ...($kernel->isDebug() ? [] : ['--no-debug'])];

        $activation = new Process([...$console, 'module:activate', 'ConsentManager', ...$options], $kernel->getProjectDir(), $environment);
        $activation->run();
        if (!$activation->isSuccessful() && !str_contains($activation->getOutput(), 'already activated')) {
            throw new \RuntimeException("Could not activate ConsentManager in the test database:\n".$activation->getOutput().$activation->getErrorOutput());
        }

        (new Process([...$console, 'cache:clear', ...$options], $kernel->getProjectDir(), $environment))->setTimeout(null)->mustRun();

        ModuleQuery::resetActivated();
    }
}
