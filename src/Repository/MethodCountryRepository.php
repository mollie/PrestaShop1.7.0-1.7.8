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

namespace Mollie\Repository;

use Db;
use DbQuery;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Repository related with payment methods country.
 */
class MethodCountryRepository
{
    public function checkIfMethodIsAvailableInCountry($methodId, $countryId)
    {
        $sql = new DbQuery();
        $sql->select('`id_mol_country`');
        $sql->from('mol_country');
        $sql->where('`id_method` = "' . (int) $methodId . '" AND ( id_country = ' . (int) $countryId . ' OR all_countries = 1)');

        return Db::getInstance()->getValue($sql);
    }

    public function checkIfCountryIsExcluded($methodId, $countryId)
    {
        $sql = new DbQuery();
        $sql->select('`id_mol_country`');
        $sql->from('mol_excluded_country');
        $sql->where('`id_method` = "' . (int) $methodId . '" AND ( id_country = ' . (int) $countryId . ' OR all_countries = 1)');

        $result = Db::getInstance()->getValue($sql);

        return is_numeric($result) && $result > 0;
    }
}
