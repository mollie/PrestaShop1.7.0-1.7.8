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

namespace Mollie\Verification;

use Mollie\Repository\PaymentMethodRepositoryInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class IsPaymentInformationAvailable
{
    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    public function __construct(PaymentMethodRepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @param int $orderId
     *
     * @return bool
     */
    public function verify(int $orderId)
    {
        return $this->hasPaymentInformation($orderId);
    }

    /**
     * @param int $orderId
     *
     * @return bool
     */
    private function hasPaymentInformation(int $orderId)
    {
        $payment = $this->paymentMethodRepository->getPaymentBy('order_id', (int) $orderId);

        return !(empty($payment) || empty($payment['transaction_id']));
    }
}
