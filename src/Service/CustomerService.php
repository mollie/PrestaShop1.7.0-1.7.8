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

use MolCustomer;
use Mollie;
use Mollie\Config\Config;
use Mollie\Exception\MollieException;
use Mollie\Factory\ModuleFactory;
use Mollie\Repository\MolCustomerRepository;
use Mollie\Utility\CustomerUtility;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomerService
{
    /**
     * @var Mollie
     */
    private $mollie;

    /**
     * @var MolCustomerRepository
     */
    private $customerRepository;

    public function __construct(ModuleFactory $moduleFactory, MolCustomerRepository $customerRepository)
    {
        $this->mollie = $moduleFactory->getModule();
        $this->customerRepository = $customerRepository;
    }

    /**
     * @return MolCustomer|null
     *
     * @throws MollieException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function processCustomerCreation(int $customerId)
    {
        if (!$this->isSingleClickPaymentEnabled()) {
            return null;
        }

        $customer = new \Customer($customerId);

        $fullName = CustomerUtility::getCustomerFullName($customer->id);
        /** @var MolCustomer|null $molCustomer */
        $molCustomer = $this->getCustomer($customerId);

        if ($molCustomer) {
            return $molCustomer;
        }

        $mollieCustomer = $this->createCustomer($fullName, $customer->email);

        $molCustomer = new MolCustomer();
        $molCustomer->name = $fullName;
        $molCustomer->email = $customer->email;
        $molCustomer->customer_id = $mollieCustomer->id;
        $molCustomer->created_at = $mollieCustomer->createdAt;

        $molCustomer->add();

        return $molCustomer;
    }

    /**
     * @return \MolCustomer|null
     *
     * @throws \PrestaShopException
     */
    public function getCustomer(int $customerId)
    {
        $customer = new \Customer($customerId);

        $fullName = CustomerUtility::getCustomerFullName($customer->id);

        /* @var MolCustomer|null $molCustomer */
        return $this->customerRepository->findOneBy(/* @phpstan-ignore-line */
            [
                'name' => $fullName,
                'email' => $customer->email,
            ]
        );
    }

    public function createCustomer($name, $email)
    {
        try {
            return $this->mollie->getApiClient()->customers->create(
                [
                    'name' => $name,
                    'email' => $email,
                ]
            );
        } catch (\Exception $e) {
            throw new MollieException('Failed to create Mollie customer', MollieException::CUSTOMER_EXCEPTION, $e);
        }
    }

    public function isSingleClickPaymentEnabled()
    {
        $isSingleClickPaymentEnabled = \Configuration::get(Config::MOLLIE_SINGLE_CLICK_PAYMENT);
        if ($isSingleClickPaymentEnabled) {
            return true;
        }

        return false;
    }
}
