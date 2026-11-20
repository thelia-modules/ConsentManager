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

namespace ConsentManager;

use ConsentManager\Service\ConsentState;
use ConsentManager\Service\DefaultCategories;
use ConsentManager\Service\ScriptGate;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Install\Database;
use Thelia\Module\BaseModule;

class ConsentManager extends BaseModule
{
    public const DOMAIN_NAME = 'consentmanager';
    public const BO_DOMAIN = 'consentmanager.bo.default';
    public const FO_DOMAIN = 'consentmanager.fo.default';

    /** Stable service ids other modules may reference without depending on this module's classes. */
    public const SCRIPT_GATE_SERVICE = 'consent_manager.script_gate';
    public const CONSENT_STATE_SERVICE = 'consent_manager.consent_state';

    public const RATE_LIMITER = 'consent_manager_record';

    public static function configureServices(ServicesConfigurator $services): void
    {
        $services->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n/*',
                __DIR__.'/Config/**/*.php',
                __DIR__.'/Tests/*',
                __DIR__.'/Model/*',
                __DIR__.'/ConsentManager.php',
            ])
            ->autowire()
            ->autoconfigure();

        $services->alias(self::SCRIPT_GATE_SERVICE, ScriptGate::class)->public();
        $services->alias(self::CONSENT_STATE_SERVICE, ConsentState::class)->public();
    }

    /**
     * Every visitor can write a proof without an account: the window caps what one
     * address can store, far above what a person changing their mind ever does.
     */
    public static function configureContainer(ContainerConfigurator $containerConfigurator): void
    {
        $containerConfigurator->extension('framework', [
            'rate_limiter' => [
                self::RATE_LIMITER => [
                    'policy' => 'sliding_window',
                    'limit' => 30,
                    'interval' => '1 hour',
                ],
            ],
        ]);
    }

    public function postActivation(?ConnectionInterface $con = null): void
    {
        if ('1' !== self::getConfigValue('is_initialized', '0')) {
            (new Database($con))->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);
            DefaultCategories::create();
            self::setConfigValue('is_initialized', '1');
        }
    }

    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $updateDirectory = __DIR__.'/Config/update';
        if (!is_dir($updateDirectory)) {
            return;
        }

        $database = new Database($con);
        foreach (Finder::create()->name('*.sql')->depth(0)->sortByName()->in($updateDirectory) as $file) {
            if (version_compare((string) $currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }
}
