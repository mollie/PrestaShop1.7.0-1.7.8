<?php

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
