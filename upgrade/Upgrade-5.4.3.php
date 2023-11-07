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

use Mollie\Adapter\ConfigurationAdapter;
use Mollie\Config\Config;
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

    updateConfigurationValues543($module);
    updateOrderStatusNames543($module);

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

function updateConfigurationValues543(Mollie $module)
{
    /** @var ConfigurationAdapter $configuration */
    $configuration = $module->getService(ConfigurationAdapter::class);

    if (
        !empty($configuration->get(Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_AUTHORIZED))
        && !empty($configuration->get(Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_SHIPPED))
        && !empty($configuration->get(Config::MOLLIE_AUTHORIZABLE_PAYMENT_INVOICE_ON_STATUS))
        && empty($configuration->get('MOLLIE_STATUS_KLARNA_AUTHORIZED'))
        && empty($configuration->get('MOLLIE_STATUS_KLARNA_SHIPPED'))
        && empty($configuration->get('MOLLIE_KLARNA_INVOICE_ON'))
    ) {
        return;
    }

    $klarnaInvoiceOn = $configuration->get('MOLLIE_KLARNA_INVOICE_ON');

    switch ($klarnaInvoiceOn) {
        case 'MOLLIE_STATUS_KLARNA_AUTHORIZED':
            $configuration->updateValue(
                Config::MOLLIE_AUTHORIZABLE_PAYMENT_INVOICE_ON_STATUS,
                Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_AUTHORIZED
            );
            break;
        case 'MOLLIE_STATUS_KLARNA_SHIPPED':
            $configuration->updateValue(
                Config::MOLLIE_AUTHORIZABLE_PAYMENT_INVOICE_ON_STATUS,
                Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_SHIPPED
            );
            break;
        default:
            $configuration->updateValue(
                Config::MOLLIE_AUTHORIZABLE_PAYMENT_INVOICE_ON_STATUS,
                Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_DEFAULT
            );
    }

    $configuration->updateValue(Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_AUTHORIZED, (int) $configuration->get('MOLLIE_STATUS_KLARNA_AUTHORIZED'));
    $configuration->updateValue(Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_SHIPPED, (int) $configuration->get('MOLLIE_STATUS_KLARNA_SHIPPED'));

    $configuration->delete('MOLLIE_STATUS_KLARNA_AUTHORIZED');
    $configuration->delete('MOLLIE_STATUS_KLARNA_SHIPPED');
    $configuration->delete('MOLLIE_KLARNA_INVOICE_ON');
}

function updateOrderStatusNames543(Mollie $module)
{
    /** @var ConfigurationAdapter $configuration */
    $configuration = $module->getService(ConfigurationAdapter::class);

    $authorizablePaymentStatusShippedId = (int) $configuration->get(Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_SHIPPED);
    $authorizablePaymentStatusShipped = new OrderState((int) $authorizablePaymentStatusShippedId);

    if (is_array($authorizablePaymentStatusShipped->name)) {
        foreach ($authorizablePaymentStatusShipped->name as $langId => $name) {
            $authorizablePaymentStatusShipped->name[$langId] = 'Order payment shipped';
        }
    } else {
        $authorizablePaymentStatusShipped->name = 'Order payment shipped';
    }

    $authorizablePaymentStatusShipped->save();

    $authorizablePaymentStatusAuthorizedId = (int) $configuration->get(Config::MOLLIE_AUTHORIZABLE_PAYMENT_STATUS_AUTHORIZED);
    $authorizablePaymentStatusAuthorized = new OrderState((int) $authorizablePaymentStatusAuthorizedId);

    if (is_array($authorizablePaymentStatusAuthorized->name)) {
        foreach ($authorizablePaymentStatusAuthorized->name as $langId => $name) {
            $authorizablePaymentStatusAuthorized->name[$langId] = 'Order payment authorized';
        }
    } else {
        $authorizablePaymentStatusAuthorized->name = 'Order payment authorized';
    }

    $authorizablePaymentStatusAuthorized->save();
}
