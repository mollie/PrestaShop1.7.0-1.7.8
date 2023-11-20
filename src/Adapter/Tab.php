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

namespace Mollie\Adapter;

use Tab as PrestashopTab;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
