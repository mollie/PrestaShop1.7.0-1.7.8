<?php
/**
 * Mollie       https://www.mollie.nl
 *
 * @author      Mollie B.V. <info@mollie.nl>
 * @copyright   Mollie B.V.
 * @license     https://github.com/mollie/PrestaShop/blob/master/LICENSE.md
 *
 * @see        https://github.com/mollie/PrestaShop
 * @codingStandardsIgnoreStart
 */

use Mollie\Bootstrap\ModuleTabs;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminMollieModuleController extends ModuleAdminController
{
    public function init()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(ModuleTabs::ADMIN_MOLLIE_SETTINGS_CONTROLLER));
    }
}
