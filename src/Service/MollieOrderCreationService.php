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

use Db;
use Exception;
use Mollie;
use Mollie\Api\Resources\Order as MollieOrderAlias;
use Mollie\Api\Resources\Payment as MolliePaymentAlias;
use Mollie\Api\Types\PaymentStatus;
use Mollie\Config\Config;
use Mollie\DTO\OrderData;
use Mollie\DTO\PaymentData;
use Mollie\Exception\OrderCreationException;
use Mollie\Factory\ModuleFactory;
use Mollie\Handler\ErrorHandler\ErrorHandler;
use Mollie\Handler\Exception\OrderExceptionHandler;
use MolPaymentMethod;
use PrestaShopException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MollieOrderCreationService
{
    /**
     * @var OrderExceptionHandler
     */
    private $exceptionHandler;
    /**
     * @var Mollie
     */
    private $module;

    public function __construct(OrderExceptionHandler $exceptionHandler, ModuleFactory $moduleFactory)
    {
        $this->exceptionHandler = $exceptionHandler;
        $this->module = $moduleFactory->getModule();
    }

    /**
     * @param PaymentData|OrderData $paymentData
     * @param MolPaymentMethod $paymentMethodObj
     *
     * @return false|MollieOrderAlias|MolliePaymentAlias
     *
     * @throws PrestaShopException
     */
    public function createMollieOrder($paymentData, $paymentMethodObj)
    {
        try {
            $apiPayment = $this->createPayment($paymentData->jsonSerialize(), $paymentMethodObj->method);
        } catch (Exception $e) {
            if ($paymentData instanceof OrderData) {
                $paymentData->setDeliveryPhoneNumber(null);
                $paymentData->setBillingPhoneNumber(null);
            }
            try {
                $apiPayment = $this->createPayment($paymentData->jsonSerialize(), $paymentMethodObj->method);
            } catch (OrderCreationException $e) {
                $errorHandler = ErrorHandler::getInstance();
                $errorHandler->handle($e, $e->getCode(), true);
            } catch (Exception $e) {
                $errorHandler = ErrorHandler::getInstance();
                $errorHandler->handle($e, $e->getCode(), true);
            }
        }

        /* @phpstan-ignore-next-line */
        return $apiPayment;
    }

    /**
     * @param PaymentData|OrderData $paymentData
     * @param MolPaymentMethod $paymentMethodObj
     *
     * @return false|MollieOrderAlias|MolliePaymentAlias
     *
     * @throws PrestaShopException
     */
    public function createMollieApplePayDirectOrder($paymentData, $paymentMethodObj)
    {
        try {
            $apiPayment = $this->createPayment($paymentData->jsonSerialize(), $paymentMethodObj->method);
        } catch (OrderCreationException $e) {
            $errorHandler = ErrorHandler::getInstance();
            $errorHandler->handle($e, $e->getCode(), true);
        } catch (Exception $e) {
            $errorHandler = ErrorHandler::getInstance();
            $errorHandler->handle($e, $e->getCode(), true);
        }

        /* @phpstan-ignore-next-line */
        return $apiPayment;
    }

    public function createMolliePayment($apiPayment, $cartId, $orderReference)
    {
        Db::getInstance()->insert(
            'mollie_payments',
            [
                'cart_id' => (int) $cartId,
                'method' => pSQL($apiPayment->method),
                'transaction_id' => pSQL($apiPayment->id),
                'order_reference' => pSQL($orderReference),
                'bank_status' => PaymentStatus::STATUS_OPEN,
                'created_at' => ['type' => 'sql', 'value' => 'NOW()'],
            ]
        );
    }

    public function updateMolliePaymentReference(string $transactionId, string $orderReference)
    {
        Db::getInstance()->update(
            'mollie_payments',
            [
                'order_reference' => pSQL($orderReference),
                'updated_at' => ['type' => 'sql', 'value' => 'NOW()'],
            ],
            'transaction_id = "' . pSQL($transactionId) . '"'
        );
    }

    /**
     * @param array $data
     * @param string $selectedApi
     *
     * @return MollieOrderAlias|MolliePaymentAlias
     *
     * @throws OrderCreationException
     */
    private function createPayment($data, $selectedApi)
    {
        try {
            if (Config::MOLLIE_ORDERS_API === $selectedApi) {
                /** @var MollieOrderAlias $payment */
                $payment = $this->module->getApiClient()->orders->create($data, ['embed' => 'payments']);
            } else {
                /** @var MolliePaymentAlias $payment */
                $payment = $this->module->getApiClient()->payments->create($data);
            }

            return $payment;
        } catch (Exception $e) {
            throw $this->exceptionHandler->handle($e);
        }
    }
}
