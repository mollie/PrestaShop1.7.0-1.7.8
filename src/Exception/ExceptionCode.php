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

namespace Mollie\Exception;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ExceptionCode
{
    // Infrastructure error codes starts from 1000

    const INFRASTRUCTURE_FAILED_TO_INSTALL_ORDER_STATE = 1001;
    const INFRASTRUCTURE_FAILED_TO_INSTALL_MODULE_TAB = 1002;

    const FAILED_TO_FIND_CUSTOMER_ADDRESS = 2001;
}
