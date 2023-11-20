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

class CouldNotInstallModule extends MollieException
{
    public static function failedToInstallOrderState(string $orderStateName, \Exception $exception): self
    {
        return new self(
            sprintf('Failed to install order state (%s).', $orderStateName),
            ExceptionCode::INFRASTRUCTURE_FAILED_TO_INSTALL_ORDER_STATE,
            $exception
        );
    }

    public static function failedToInstallModuleTab(\Exception $exception, string $moduleTab): self
    {
        return new self(
            sprintf('Failed to install module tab (%s)', $moduleTab),
            ExceptionCode::INFRASTRUCTURE_FAILED_TO_INSTALL_MODULE_TAB,
            $exception
        );
    }
}
