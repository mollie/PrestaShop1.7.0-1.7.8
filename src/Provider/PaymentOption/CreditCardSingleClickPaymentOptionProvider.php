<?php
/**
 * Copyright (c) 2012-2020, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @author     Mollie B.V. <info@mollie.nl>
 * @copyright  Mollie B.V.
 * @license    Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 *
 * @category   Mollie
 *
 * @see       https://www.mollie.nl
 * @codingStandardsIgnoreStart
 */

namespace Mollie\Provider\PaymentOption;

use MolCustomer;
use Mollie;
use Mollie\Adapter\ConfigurationAdapter;
use Mollie\Adapter\Context;
use Mollie\Config\Config;
use Mollie\Factory\ModuleFactory;
use Mollie\Provider\CreditCardLogoProvider;
use Mollie\Provider\OrderTotal\OrderTotalProviderInterface;
use Mollie\Provider\PaymentFeeProviderInterface;
use Mollie\Repository\MolCustomerRepository;
use Mollie\Service\LanguageService;
use Mollie\Utility\CustomerUtility;
use MolPaymentMethod;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CreditCardSingleClickPaymentOptionProvider implements PaymentOptionProviderInterface
{
    const FILE_NAME = 'CreditCardSingleClickPaymentOptionProvider';

    /**
     * @var Mollie
     */
    private $module;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var CreditCardLogoProvider
     */
    private $creditCardLogoProvider;

    /**
     * @var OrderTotalProviderInterface
     */
    private $orderTotalProvider;

    /**
     * @var PaymentFeeProviderInterface
     */
    private $paymentFeeProvider;

    /**
     * @var LanguageService
     */
    private $languageService;
    /**
     * @var MolCustomerRepository
     */
    private $customerRepository;
    /** @var ConfigurationAdapter */
    private $configurationAdapter;

    public function __construct(
        ModuleFactory $moduleFactory,
        Context $context,
        CreditCardLogoProvider $creditCardLogoProvider,
        OrderTotalProviderInterface $orderTotalProvider,
        PaymentFeeProviderInterface $paymentFeeProvider,
        LanguageService $languageService,
        MolCustomerRepository $customerRepository,
        ConfigurationAdapter $configurationAdapter
    ) {
        $this->module = $moduleFactory->getModule();
        $this->context = $context;
        $this->creditCardLogoProvider = $creditCardLogoProvider;
        $this->orderTotalProvider = $orderTotalProvider;
        $this->paymentFeeProvider = $paymentFeeProvider;
        $this->languageService = $languageService;
        $this->customerRepository = $customerRepository;
        $this->configurationAdapter = $configurationAdapter;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentOption(MolPaymentMethod $paymentMethod): PaymentOption
    {
        $paymentOption = new PaymentOption();

        $paymentOption->setCallToActionText(
            $paymentMethod->title ?:
                $this->languageService->lang($paymentMethod->method_name)
        );
        $paymentOption->setModuleName($this->module->name);
        $paymentOption->setAction($this->context->getLink()->getModuleLink(
            'mollie',
            'payScreen',
            ['method' => $paymentMethod->getPaymentMethodName(), 'rand' => Mollie\Utility\TimeUtility::getCurrentTimeStamp()],
            true
        ));
        $fullName = CustomerUtility::getCustomerFullName($this->context->getCustomerId());

        /** @var MolCustomer|null $molCustomer */
        $molCustomer = $this->customerRepository->findOneBy(
            [
                'name' => $fullName,
                'email' => $this->context->getCustomerEmail(),
            ]
        );

        $useSavedUser = (bool) (int) ($this->configurationAdapter->get(Config::MOLLIE_SINGLE_CLICK_PAYMENT) && $molCustomer);

        $paymentOption->setInputs([
            [
                'type' => 'hidden',
                'name' => 'mollieCardToken',
                'value' => '',
            ],
            [
                'type' => 'hidden',
                'name' => 'mollieSaveCard',
                'value' => '',
            ],
            [
                'type' => 'hidden',
                'name' => 'mollieCustomerExists',
                'value' => (bool) $molCustomer,
            ],
            [
                'type' => 'hidden',
                'name' => 'mollieUseSavedCard',
                'value' => $useSavedUser,
            ],
        ]);

        $this->context->getSmarty()->assign([
            'price' => $this->orderTotalProvider->getOrderTotal(),
            'priceSign' => $this->context->getCurrencySign(),
            'methodId' => $paymentMethod->getPaymentMethodName(),
            'isSingleClickPayment' => (bool) (int) $this->configurationAdapter->get(Mollie\Config\Config::MOLLIE_SINGLE_CLICK_PAYMENT),
            'mollieUseSavedCard' => $useSavedUser,
            'isGuest' => $this->context->isCustomerGuest(),
        ]);
        $paymentOption->setLogo($this->creditCardLogoProvider->getMethodOptionLogo($paymentMethod));

        $paymentOption->setAdditionalInformation($this->module->display(
            $this->module->getPathUri(), 'views/templates/hook/mollie_single_click.tpl'
        ));

        $paymentFeeData = $this->paymentFeeProvider->getPaymentFee($paymentMethod, $this->orderTotalProvider->getOrderTotal());

        if ($paymentFeeData->isActive()) {
            $paymentOption->setInputs(
                array_merge($paymentOption->getInputs(), [
                    [
                        'type' => 'hidden',
                        'name' => 'payment-fee-price',
                        'value' => $paymentFeeData->getPaymentFeeTaxIncl(),
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'payment-fee-price-display',
                        'value' => sprintf(
                            $this->module->l('Payment Fee: %1s', self::FILE_NAME),
                            Tools::displayPrice($paymentFeeData->getPaymentFeeTaxIncl())
                        ),
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'payment-method-id',
                        'value' => $paymentMethod->id,
                    ],
                ])
            );
        }

        return $paymentOption;
    }
}
