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

namespace Mollie\Service;

use Configuration;
use Mollie\Adapter\Context;
use Mollie\Config\Config;
use Mollie\DTO\PaymentFeeData;
use Mollie\Provider\PaymentFeeProviderInterface;
use Mollie\Repository\PaymentMethodRepositoryInterface;
use MolOrderPaymentFee;
use MolPaymentMethod;
use PrestaShopException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderPaymentFeeService
{
    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;
    /** @var PaymentFeeProviderInterface */
    private $paymentFeeProvider;
    /** @var Context */
    private $context;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentFeeProviderInterface $paymentFeeProvider,
        Context $context
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentFeeProvider = $paymentFeeProvider;
        $this->context = $context;
    }

    public function createOrderPaymentFee(int $orderId, int $cartId, PaymentFeeData $paymentFeeData)
    {
        $molOrderPaymentFee = new MolOrderPaymentFee();

        $molOrderPaymentFee->id_cart = $cartId;
        $molOrderPaymentFee->id_order = $orderId;
        $molOrderPaymentFee->fee_tax_incl = $paymentFeeData->getPaymentFeeTaxIncl();
        $molOrderPaymentFee->fee_tax_excl = $paymentFeeData->getPaymentFeeTaxExcl();

        try {
            $molOrderPaymentFee->add();
        } catch (\Exception $e) {
            $errorHandler = \Mollie\Handler\ErrorHandler\ErrorHandler::getInstance();
            $errorHandler->handle($e, $e->getCode(), false);

            // TODO use custom exceptions
            throw new PrestaShopException('Can\'t save Order fee');
        }
    }

    public function getPaymentFee(float $totalAmount, string $method): PaymentFeeData
    {
        // TODO order and payment fee in same service? Separate logic as this is probably used in cart context

        $environment = Configuration::get(Config::MOLLIE_ENVIRONMENT);
        $paymentId = $this->paymentMethodRepository->getPaymentMethodIdByMethodId($method, $environment, $this->context->getShopId());
        $molPaymentMethod = new MolPaymentMethod($paymentId);

        return $this->paymentFeeProvider->getPaymentFee($molPaymentMethod, $totalAmount);
    }
}
