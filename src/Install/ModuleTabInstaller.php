<?php

namespace Mollie\Install;

use Mollie\Adapter\Language;
use Mollie\Adapter\Tab;
use Mollie\Bootstrap\ModuleTabs;
use Mollie\Exception\CouldNotInstallModule;
use Mollie\Factory\ModuleFactory;
use Mollie\Service\EntityManager\EntityManagerInterface;
use Mollie\Service\EntityManager\ObjectModelUnitOfWork;

class ModuleTabInstaller
{
    private $tab;
    private $language;
    private $moduleTabs;
    private $entityManager;
    private $module;

    public function __construct(
        ModuleFactory $moduleFactory,
        Tab $tab,
        Language $language,
        ModuleTabs $moduleTabs,
        EntityManagerInterface $entityManager
    ) {
        $this->tab = $tab;
        $this->language = $language;
        $this->moduleTabs = $moduleTabs;
        $this->entityManager = $entityManager;
        $this->module = $moduleFactory->getModule();
    }

    /**
     * @throws CouldNotInstallModule
     */
    public function init()
    {
        $tabs = $this->moduleTabs->getTabs();

        foreach ($tabs as $tab) {
            if ($this->tab->getIdFromClassName($tab['class_name'])) {
                continue;
            }

            $this->installTab(
                $tab['class_name'],
                $tab['parent_class_name'],
                $tab['name'],
                $tab['icon'],
                $tab['visible']
            );
        }
    }

    /**
     * @throws CouldNotInstallModule
     */
    private function installTab(
        string $className,
        string $parent,
        array $name,
        string $icon,
        bool $visible
    ) {
        $idParent = $this->tab->getIdFromClassName($parent . '_MTR');

        if (!$idParent) {
            $idParent = $this->tab->getIdFromClassName($parent);
        }

        $moduleTab = $this->tab->initTab();
        $moduleTab->class_name = $className;
        $moduleTab->id_parent = $idParent;
        $moduleTab->module = $this->module->name;
        $moduleTab->icon = $icon;
        $moduleTab->active = $visible;

        $languages = $this->language->getAllLanguages();
        foreach ($languages as $language) {
            $moduleTab->name[$language['id_lang']] = isset($name[$language['iso_code']]) ? pSQL($name[$language['iso_code']]) : pSQL($name['en']);
        }

        try {
            $this->entityManager->persist($moduleTab, ObjectModelUnitOfWork::UNIT_OF_WORK_SAVE);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            throw CouldNotInstallModule::failedToInstallModuleTab($exception, $className);
        }
    }
}
