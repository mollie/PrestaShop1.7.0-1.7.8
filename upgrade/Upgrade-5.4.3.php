<?php

/**
 * Mollie       https://www.mollie.nl
 *
 * @author      Mollie B.V. <info@mollie.nl>
 * @copyright   Mollie B.V.
 * @license     https://github.com/mollie/PrestaShop/blob/master/LICENSE.md
 *
 * @see        https://github.com/mollie/PrestaShop
 */

use Mollie\Install\ModuleTabInstaller;
use Mollie\Logger\PrestaLoggerInterface;
use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;
use PrestaShop\PsAccountsInstaller\Installer\Installer as PsAccountsInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_5_4_3(Mollie $module): bool
{
    /** @var ModuleTabInstaller $moduleTabInstaller */
    $moduleTabInstaller = $module->getService(ModuleTabInstaller::class);

    /** @var PrestaLoggerInterface $logger */
    $logger = $module->getService(PrestaLoggerInterface::class);

    try {
        $moduleTabInstaller->init();
    } catch (\Throwable $exception) {
        $logger->error('Failed to install module tabs. Please contact support.', [
            'Exception message' => $exception->getMessage(),
            'Exception code' => $exception->getCode(),
        ]);

        return false;
    }

    return installPsAccounts543($module)
        && installCloudSync543($module);
}

function installPsAccounts543(Mollie $module): bool
{
    /** @var PrestaLoggerInterface $logger */
    $logger = $module->getService(PrestaLoggerInterface::class);

    try {
        /** @var PsAccountsInstaller $prestashopAccountsInstaller */
        $prestashopAccountsInstaller = $module->getService(PsAccountsInstaller::class);

        if (!$prestashopAccountsInstaller->install()) {
            $logger->error('Failed to install Prestashop Accounts module. Please contact support.');

            return false;
        }
    } catch (\Throwable $exception) {
        $logger->error('Failed to install Prestashop Accounts module. Please contact support.', [
            'Exception message' => $exception->getMessage(),
            'Exception code' => $exception->getCode(),
        ]);

        return false;
    }

    return true;
}

function installCloudSync543(Mollie $module): bool
{
    /** @var PrestaLoggerInterface $logger */
    $logger = $module->getService(PrestaLoggerInterface::class);

    $moduleManager = ModuleManagerBuilder::getInstance()->build();

    try {
        if (
            $moduleManager->isInstalled('ps_eventbus') &&
            !$moduleManager->isEnabled('ps_eventbus')
        ) {
            $moduleManager->enable('ps_eventbus');
        }

        $moduleManager->install('ps_eventbus');
    } catch (Exception $exception) {
        $logger->error('Failed to install/upgrade Prestashop event bus module. Please contact support.', [
            'Exception message' => $exception->getMessage(),
            'Exception code' => $exception->getCode(),
        ]);

        return false;
    }

    return true;
}
