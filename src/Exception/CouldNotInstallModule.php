<?php

namespace Mollie\Exception;

class CouldNotInstallModule extends MollieException
{
    const FAILED_TO_INSTALL_ORDER_STATE = 1;

    public static function failedToInstallOrderState(string $orderStateName, \Exception $exception): CouldNotInstallModule
    {
        return new self(
            sprintf('Failed to install order state (%s).', $orderStateName),
            self::FAILED_TO_INSTALL_ORDER_STATE,
            $exception
        );
    }
}
