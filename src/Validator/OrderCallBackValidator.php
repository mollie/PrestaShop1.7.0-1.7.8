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

namespace Mollie\Validator;

use Mollie;
use Mollie\Adapter\Context;
use Mollie\Factory\ModuleFactory;
use Mollie\Utility\SecureKeyUtility;

class OrderCallBackValidator
{
    /**
     * @var Mollie
     */
    private $module;
    /** @var Context */
    private $context;

    public function __construct(Context $context, ModuleFactory $moduleFactory)
    {
        $this->context = $context;
        $this->module = $moduleFactory->getModule();
    }

    public function validate($key, $cartId)
    {
        return $this->isSignatureMatches($key, $cartId);
    }

    /**
     * Checks If Signature Matches.
     *
     * @param string $key
     * @param int $cartId
     *
     * @return bool
     */
    public function isSignatureMatches($key, $cartId)
    {
        return $key === SecureKeyUtility::generateReturnKey(
                $this->context->getCustomerId(),
                $cartId,
                $this->module->name
            );
    }
}
