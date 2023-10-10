<?php

namespace Mollie\Bootstrap;

use Mollie\Factory\ModuleFactory;

class ModuleTabs
{
    const SELF_NAME = 'ModuleTabs';

    const ADMIN_MOLLIE_CONTROLLER = 'AdminMollieModule';
    const ADMIN_MOLLIE_AJAX_CONTROLLER = 'AdminMollieAjax';
    const ADMIN_MOLLIE_SETTINGS_CONTROLLER = 'AdminMollieSettings';

    /** @var \Mollie */
    private $module;

    public function __construct(ModuleFactory $moduleFactory)
    {
        $this->module = $moduleFactory->getModule();
    }

    public function getTabs(): array
    {
        // TODO legacy tab translator

        return [
            [
                'name' => [
                    'en' => $this->module->l('AJAX', self::SELF_NAME),
                    'en-US' => $this->module->l('AJAX', self::SELF_NAME),
                ],
                'class_name' => self::ADMIN_MOLLIE_AJAX_CONTROLLER,
                'parent_class_name' => '',
                'ParentClassName' => '',
                'module_tab' => true,
                'visible' => false,
            ],
            [
                'name' => [
                    'en' => $this->module->l('Settings', self::SELF_NAME),
                    'en-US' => $this->module->l('Settings', self::SELF_NAME),
                ],
                'class_name' => self::ADMIN_MOLLIE_SETTINGS_CONTROLLER,
                'parent_class_name' => '',
                'ParentClassName' => '',
                'module_tab' => true,
                'visible' => false,
            ],
        ];
    }
}
