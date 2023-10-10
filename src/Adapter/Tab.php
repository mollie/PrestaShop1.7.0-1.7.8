<?php

namespace Mollie\Adapter;

use Tab as PrestashopTab;

class Tab
{
    public function initTab(int $idTab = null): PrestashopTab
    {
        return new PrestashopTab($idTab);
    }

    /**
     * @param string|null $parent
     *
     * @return int|null
     */
    public function getIdFromClassName($parent)
    {
        $tabId = (int) PrestashopTab::getIdFromClassName($parent);

        if (!$tabId) {
            return null;
        }

        return $tabId;
    }
}
