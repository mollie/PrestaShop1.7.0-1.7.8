<?php

namespace Mollie\Tests\Integration\Install;

use Mollie\Bootstrap\ModuleTabs;
use Mollie\Install\ModuleTabInstaller;
use Mollie\Tests\Integration\BaseTestCase;

class ModuleTabInstallerTest extends BaseTestCase
{
    protected function setUp()
    {
        parent::setUp();

        \Db::getInstance()->delete('tab', '`module` = "mollie"');
        \Db::getInstance()->delete('authorization_role', '`slug` LIKE "%mollie%"');
    }

    public function testItSuccessfullyHandlesModuleTabInstall()
    {
        /** @var ModuleTabs $moduleTabs */
        $moduleTabs = $this->getService(ModuleTabs::class);

        /** @var ModuleTabInstaller $moduleTabInstaller */
        $moduleTabInstaller = $this->getService(ModuleTabInstaller::class);

        $tabs = $moduleTabs->getTabs();

        foreach ($tabs as $tab) {
            $this->assertDatabaseHasNot(\Tab::class, [
                'class_name' => $tab['class_name'],
            ]);
        }

        $moduleTabInstaller->init();

        foreach ($tabs as $tab) {
            $this->assertDatabaseHas(\Tab::class, [
                'class_name' => $tab['class_name'],
            ]);
        }
    }
}
