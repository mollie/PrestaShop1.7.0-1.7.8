<?php

namespace Mollie\Exception;

class ExceptionCode
{
    // Infrastructure error codes starts from 1000

    const INFRASTRUCTURE_FAILED_TO_INSTALL_ORDER_STATE = 1001;
    const INFRASTRUCTURE_FAILED_TO_INSTALL_MODULE_TAB = 1002;

    const FAILED_TO_FIND_CUSTOMER_ADDRESS = 2001;
}
