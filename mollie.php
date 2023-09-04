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

use Mollie\Adapter\ConfigurationAdapter;
use Mollie\Adapter\ToolsAdapter;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Builder\Content\BaseInfoBlock;
use Mollie\Builder\Content\LogoInfoBlock;
use Mollie\Builder\Content\UpdateMessageInfoBlock;
use Mollie\Builder\FormBuilder;
use Mollie\Builder\InvoicePdfTemplateBuilder;
use Mollie\Config\Config;
use Mollie\Exception\ShipmentCannotBeSentException;
use Mollie\Grid\Definition\Modifier\OrderGridDefinitionModifier;
use Mollie\Grid\Query\Modifier\OrderGridQueryModifier;
use Mollie\Handler\PaymentOption\PaymentOptionHandlerInterface;
use Mollie\Handler\Shipment\ShipmentSenderHandlerInterface;
use Mollie\Install\Installer;
use Mollie\Install\Uninstall;
use Mollie\Logger\PrestaLoggerInterface;
use Mollie\Presenter\OrderListActionBuilder;
use Mollie\Provider\ProfileIdProviderInterface;
use Mollie\Repository\ModuleRepository;
use Mollie\Repository\MolOrderPaymentFeeRepositoryInterface;
use Mollie\Repository\PaymentMethodRepositoryInterface;
use Mollie\Service\ApiKeyService;
use Mollie\Service\Content\TemplateParserInterface;
use Mollie\Service\ErrorDisplayService;
use Mollie\Service\ExceptionService;
use Mollie\Service\LanguageService;
use Mollie\Service\MollieOrderInfoService;
use Mollie\Service\MolliePaymentMailService;
use Mollie\Service\PaymentMethodService;
use Mollie\Service\SettingsSaveService;
use Mollie\Service\ShipmentServiceInterface;
use Mollie\ServiceProvider\LeagueServiceContainerProvider;
use Mollie\Tracker\Segment;
use Mollie\Utility\PsVersionUtility;
use Mollie\Validator\OrderConfMailValidator;
use Mollie\Verification\IsPaymentInformationAvailable;
use PrestaShop\PrestaShop\Core\Localization\Locale\Repository;

require_once __DIR__ . '/vendor/autoload.php';

class Mollie extends PaymentModule
{
    const DISABLE_CACHE = true;

    /** @var \Mollie\Api\MollieApiClient|null */
    public $api = null;

    /** @var string */
    public static $selectedApi;

    /** @var bool Indicates whether the Smarty cache has been cleared during updates */
    public static $cacheCleared;

    // The Addons version does not include the GitHub updater
    const ADDONS = false;

    const SUPPORTED_PHP_VERSION = '70080';

    const ADMIN_MOLLIE_CONTROLLER = 'AdminMollieModuleController';
    const ADMIN_MOLLIE_AJAX_CONTROLLER = 'AdminMollieAjaxController';

    /** @var LeagueServiceContainerProvider */
    private $containerProvider;

    /**
     * Mollie constructor.
     */
    public function __construct()
    {
        $this->name = 'mollie';
        $this->tab = 'payments_gateways';
        $this->version = '5.4.3';
        $this->author = 'Mollie B.V.';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->module_key = 'a48b2f8918358bcbe6436414f48d8915';

        parent::__construct();

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => '1.7.8.9'];
        $this->displayName = $this->l('Mollie');
        $this->description = $this->l('Mollie Payments');

        $this->loadEnv();
        new \Mollie\Handler\ErrorHandler\ErrorHandler($this);
    }

    public function getService(string $serviceName)
    {
        if ($this->containerProvider === null) {
            $this->containerProvider = new LeagueServiceContainerProvider();
        }

        return $this->containerProvider->getService($serviceName);
    }

    private function loadEnv()
    {
        if (!class_exists('\Dotenv\Dotenv')) {
            return;
        }

        if (file_exists(_PS_MODULE_DIR_ . 'mollie/.env')) {
            $dotenv = \Dotenv\Dotenv::create(_PS_MODULE_DIR_ . 'mollie/', '.env');
            /* @phpstan-ignore-next-line */
            $dotenv->load();

            return;
        }
        if (file_exists(_PS_MODULE_DIR_ . 'mollie/.env.dist')) {
            $dotenv = \Dotenv\Dotenv::create(_PS_MODULE_DIR_ . 'mollie/', '.env.dist');
            /* @phpstan-ignore-next-line */
            $dotenv->load();
        }
    }

    /**
     * Installs the Mollie Payments Module.
     *
     * @return bool
     */
    public function install()
    {
        if (!$this->isPhpVersionCompliant()) {
            $this->_errors[] = $this->l('You\'re using an outdated PHP version. Upgrade your PHP version to use this module. The Mollie module supports versions PHP 7.0.8 and higher.');

            return false;
        }

        if (!parent::install()) {
            $this->_errors[] = $this->l('Unable to install module');

            return false;
        }

        /** @var Installer $installer */
        $installer = $this->getService(Installer::class);
        if (!$installer->install()) {
            $this->_errors = array_merge($this->_errors, $installer->getErrors());

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        /** @var Uninstall $uninstall */
        $uninstall = $this->getService(Uninstall::class);
        if (!$uninstall->uninstall()) {
            $this->_errors[] = $uninstall->getErrors();

            return false;
        }

        return parent::uninstall();
    }

    public function getApiClient(int $shopId = null)
    {
        if (!$this->api) {
            $this->setApiKey($shopId);
        }

        return $this->api;
    }

    public function enable($force_all = false)
    {
        if (!$this->isPhpVersionCompliant()) {
            $this->_errors[] = $this->l('You\'re using an outdated PHP version. Upgrade your PHP version to use this module. The Mollie module supports versions PHP 7.0.8 and higher.');

            return false;
        }

        return parent::enable($force_all);
    }

    /**
     * @return mixed
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @return string|void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getContent()
    {
        if (Tools::getValue('ajax')) {
            header('Content-Type: application/json;charset=UTF-8');

            if (!method_exists($this, 'displayAjax' . Tools::ucfirst(Tools::getValue('action')))) {
                exit(json_encode([
                    'success' => false,
                ]));
            }
            exit(json_encode($this->{'displayAjax' . Tools::ucfirst(Tools::getValue('action'))}()));
        }
        /** @var ModuleRepository $moduleRepository */
        $moduleRepository = $this->getService(ModuleRepository::class);
        $moduleDatabaseVersion = $moduleRepository->getModuleDatabaseVersion($this->name);
        $needsUpgrade = Tools::version_compare($this->version, $moduleDatabaseVersion, '>');
        if ($needsUpgrade) {
            $this->context->controller->errors[] = $this->l('Please upgrade Mollie module');

            return;
        }

        $isShopContext = Shop::getContext() === Shop::CONTEXT_SHOP;

        if (!$isShopContext) {
            $this->context->controller->errors[] = $this->l('Select the shop that you want to configure');

            return;
        }

        /** @var TemplateParserInterface $templateParser */
        $templateParser = $this->getService(TemplateParserInterface::class);

        $isSubmitted = (bool) Tools::isSubmit("submit{$this->name}");

        /* @phpstan-ignore-next-line */
        if (false === Configuration::get(Mollie\Config\Config::MOLLIE_STATUS_AWAITING) && !$isSubmitted) {
            $this->context->controller->errors[] = $this->l('Select an order status for \"Status for Awaiting payments\" in the \"Advanced settings\" tab');
        }

        $errors = [];

        if (Tools::isSubmit("submit{$this->name}")) {
            /** @var SettingsSaveService $saveSettingsService */
            $saveSettingsService = $this->getService(SettingsSaveService::class);
            $resultMessages = $saveSettingsService->saveSettings($errors);
            if (!empty($errors)) {
                $this->context->controller->errors = $resultMessages;
            } else {
                $this->context->controller->confirmations = $resultMessages;
            }
        }

        Media::addJsDef([
            'description_message' => addslashes($this->l('Enter a description')),
            'min_amount_message' => addslashes($this->l('You have entered incorrect min amount')),
            'max_amount_message' => addslashes($this->l('You have entered incorrect max amount')),

            'payment_api' => addslashes(Mollie\Config\Config::MOLLIE_PAYMENTS_API),
            'ajaxUrl' => addslashes($this->context->link->getAdminLink('AdminMollieAjax')),
        ]);

        /* Custom logo JS vars*/
        Media::addJsDef([
            'image_size_message' => addslashes($this->l('Upload an image %s%x%s1%')),
            'not_valid_file_message' => addslashes($this->l('Invalid file: %s%')),
        ]);

        $this->context->controller->addJS($this->getPathUri() . 'views/js/method_countries.js');
        $this->context->controller->addJS($this->getPathUri() . 'views/js/validation.js');
        $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/settings.js');
        $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/custom_logo.js');
        $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/upgrade_notice.js');
        $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/api_key_test.js');
        $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/init_mollie_account.js');
        $this->context->controller->addCSS($this->getPathUri() . 'views/css/mollie.css');
        $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/logo_input.css');

        $html = $templateParser->parseTemplate(
            $this->context->smarty,
            $this->getService(LogoInfoBlock::class),
            $this->getLocalPath() . 'views/templates/admin/logo.tpl'
        );

        /** @var UpdateMessageInfoBlock $updateMessageInfoBlock */
        $updateMessageInfoBlock = $this->getService(UpdateMessageInfoBlock::class);
        $updateMessageInfoBlockData = $updateMessageInfoBlock->setAddons(self::ADDONS);

        $html .= $templateParser->parseTemplate(
            $this->context->smarty,
            $updateMessageInfoBlockData,
            $this->getLocalPath() . 'views/templates/admin/updateMessage.tpl'
        );

        /** @var BaseInfoBlock $baseInfoBlock */
        $baseInfoBlock = $this->getService(BaseInfoBlock::class);
        $this->context->smarty->assign($baseInfoBlock->buildParams());

        /** @var FormBuilder $settingsFormBuilder */
        $settingsFormBuilder = $this->getService(FormBuilder::class);

        try {
            $html .= $settingsFormBuilder->buildSettingsForm();
        } catch (PrestaShopDatabaseException $e) {
            $errorHandler = \Mollie\Handler\ErrorHandler\ErrorHandler::getInstance();
            $errorHandler->handle($e, $e->getCode(), false);
            $this->context->controller->errors[] = $this->l('The database tables are missing. Reset the module.');
        }

        return $html;
    }

    /**
     * @param string $str
     *
     * @return string
     *
     * @deprecated
     */
    public function lang($str)
    {
        /** @var LanguageService $langService */
        $langService = $this->getService(LanguageService::class);
        $lang = $langService->getLang();
        if (array_key_exists($str, $lang)) {
            return $lang[$str];
        }

        return $str;
    }

    public function hookDisplayHeader(array $params)
    {
        if ($this->context->controller->php_self !== 'order') {
            return;
        }

        $apiClient = $this->getApiClient();

        if (!$apiClient) {
            return '';
        }

        /** @var ProfileIdProviderInterface $profileIdProvider */
        $profileIdProvider = $this->getService(ProfileIdProviderInterface::class);

        Media::addJsDef([
            'profileId' => $profileIdProvider->getProfileId($apiClient),
            'isoCode' => $this->context->language->locale,
            'isTestMode' => \Mollie\Config\Config::isTestMode(),
        ]);
        $this->context->controller->registerJavascript(
            'mollie_iframe_js',
            'https://js.mollie.com/v1/mollie.js',
            ['server' => 'remote', 'position' => 'bottom', 'priority' => 150]
        );
        $this->context->controller->addJS("{$this->_path}views/js/front/mollie_iframe.js");
        $this->context->controller->addJS("{$this->_path}views/js/front/mollie_single_click.js");
        $this->context->controller->addJS("{$this->_path}views/js/front/bancontact/qr_code.js");
        $this->context->controller->addCSS($this->getPathUri() . 'views/css/front/bancontact_qr_code.css');

        Media::addJsDef([
            'ajaxUrl' => $this->context->link->getModuleLink('mollie', 'ajax'),
            'bancontactAjaxUrl' => $this->context->link->getModuleLink('mollie', 'bancontactAjax'),
        ]);
        $this->context->controller->addJS("{$this->_path}views/js/front/mollie_error_handle.js");
        $this->context->controller->addCSS("{$this->_path}views/css/mollie_iframe.css");
        if (Configuration::get('PS_SSL_ENABLED_EVERYWHERE')) {
            $this->context->controller->addJS($this->getPathUri() . 'views/js/apple_payment.js');
        }
        $this->context->smarty->assign([
            'custom_css' => Configuration::get(Mollie\Config\Config::MOLLIE_CSS),
        ]);

        $this->context->controller->addJS("{$this->_path}views/js/front/payment_fee.js");

        return $this->display(__FILE__, 'views/templates/front/custom_css.tpl');
    }

    /**
     * @throws PrestaShopException
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        /** @var ErrorDisplayService $errorDisplayService */
        $errorDisplayService = $this->getService(ErrorDisplayService::class);

        /** @var PaymentMethodRepositoryInterface $methodRepository */
        $methodRepository = $this->getService(PaymentMethodRepositoryInterface::class);

        /** @var ConfigurationAdapter $configuration */
        $configuration = $this->getService(ConfigurationAdapter::class);

        $controller = $this->context->controller;

        if ($controller instanceof CartControllerCore) {
            $errorDisplayService->showCookieError('mollie_payment_canceled_error');
        }

        /** @var ?MolPaymentMethod $paymentMethod */
        $paymentMethod = $methodRepository->findOneBy(
            [
                'id_method' => Config::MOLLIE_METHOD_ID_APPLE_PAY,
                'live_environment' => Configuration::get(Config::MOLLIE_ENVIRONMENT),
            ]
        );

        if (!$paymentMethod || !$paymentMethod->enabled) {
            return;
        }

        $isApplePayDirectProductEnabled = (int) $configuration->get(Config::MOLLIE_APPLE_PAY_DIRECT_PRODUCT);
        $isApplePayDirectCartEnabled = (int) $configuration->get(Config::MOLLIE_APPLE_PAY_DIRECT_CART);

        $canDisplayInProductPage = $controller instanceof ProductControllerCore && $isApplePayDirectProductEnabled;
        $canDisplayInCartPage = $controller instanceof CartControllerCore && $isApplePayDirectCartEnabled;

        if (!$canDisplayInProductPage && !$canDisplayInCartPage) {
            return;
        }

        Media::addJsDef([
            'countryCode' => $this->context->country->iso_code,
            'currencyCode' => $this->context->currency->iso_code,
            'totalLabel' => $this->context->shop->name,
            'customerId' => $this->context->customer->id ?? 0,
            'ajaxUrl' => $this->context->link->getModuleLink('mollie', 'applePayDirectAjax'),
            'cartId' => $this->context->cart->id,
            'applePayButtonStyle' => (int) Configuration::get(Config::MOLLIE_APPLE_PAY_DIRECT_STYLE),
        ]);

        $this->context->controller->addCSS($this->getPathUri() . 'views/css/front/apple_pay_direct.css');

        if ($controller instanceof ProductControllerCore) {
            $this->context->controller->addJS($this->getPathUri() . 'views/js/front/applePayDirect/applePayDirectProduct.js');
        }

        if ($controller instanceof CartControllerCore) {
            $this->context->controller->addJS($this->getPathUri() . 'views/js/front/applePayDirect/applePayDirectCart.js');
        }
    }

    /**
     * Add custom JS && CSS to admin controllers.
     */
    public function hookActionAdminControllerSetMedia()
    {
        $currentController = Tools::getValue('controller');

        if ('AdminOrders' === $currentController) {
            Media::addJsDef([
                'mollieHookAjaxUrl' => $this->context->link->getAdminLink('AdminMollieAjax'),
            ]);
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/order-list.css');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/order_list.js');

            if (Tools::isSubmit('addorder') || version_compare(_PS_VERSION_, '1.7.7.0', '>=')) {
                Media::addJsDef([
                    'molliePendingStatus' => Configuration::get(\Mollie\Config\Config::MOLLIE_STATUS_AWAITING),
                    'isPsVersion177' => version_compare(_PS_VERSION_, '1.7.7.0', '>='),
                ]);
                $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/order_add.js');
            }
        }

        $moduleName = Tools::getValue('configure');

        // We are on module configuration page
        if ($this->name === $moduleName && 'AdminModules' === $currentController) {
            Media::addJsDef([
                'paymentMethodTaxRulesGroupIdConfig' => Config::MOLLIE_METHOD_TAX_RULES_GROUP_ID,
                'paymentMethodSurchargeFixedAmountTaxInclConfig' => Config::MOLLIE_METHOD_SURCHARGE_FIXED_AMOUNT_TAX_INCL,
                'paymentMethodSurchargeFixedAmountTaxExclConfig' => Config::MOLLIE_METHOD_SURCHARGE_FIXED_AMOUNT_TAX_EXCL,
            ]);

            $this->context->controller->addJqueryPlugin('sortable');
            $this->context->controller->addJS($this->getPathUri() . 'views/js/admin/payment_methods.js');
            $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/payment_methods.css');
        }
    }

    /**
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->getPathUri() . 'views/css/admin/menu.css');

        $html = '';
        if ($this->context->controller->controller_name === 'AdminOrders') {
            $this->context->smarty->assign([
                'mollieProcessUrl' => $this->context->link->getAdminLink('AdminModules', true) . '&configure=mollie&ajax=1',
                'mollieCheckMethods' => Mollie\Utility\TimeUtility::getCurrentTimeStamp() > ((int) Configuration::get(Mollie\Config\Config::MOLLIE_METHODS_LAST_CHECK) + Mollie\Config\Config::MOLLIE_METHODS_CHECK_INTERVAL),
            ]);
            $html .= $this->display(__FILE__, 'views/templates/admin/ordergrid.tpl');
            if (Tools::isSubmit('addorder') || version_compare(_PS_VERSION_, '1.7.7.0', '>=')) {
                $html .= $this->display($this->getPathUri(), 'views/templates/admin/email_checkbox.tpl');
            }
        }

        return $html;
    }

    /**
     * @param array $params Hook parameters
     *
     * @return string|bool Hook HTML
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrder($params)
    {
        /** @var PaymentMethodRepositoryInterface $paymentMethodRepo */
        $paymentMethodRepo = $this->getService(PaymentMethodRepositoryInterface::class);

        /** @var ShipmentServiceInterface $shipmentService */
        $shipmentService = $this->getService(ShipmentServiceInterface::class);

        $cartId = Cart::getCartIdByOrderId((int) $params['id_order']);
        $transaction = $paymentMethodRepo->getPaymentBy('cart_id', (string) $cartId);
        if (empty($transaction)) {
            return false;
        }
        $currencies = [];
        foreach (Currency::getCurrencies() as $currency) {
            $currencies[Tools::strtoupper($currency['iso_code'])] = [
                'name' => $currency['name'],
                'iso_code' => Tools::strtoupper($currency['iso_code']),
                'sign' => $currency['sign'],
                'blank' => (bool) isset($currency['blank']) ? $currency['blank'] : true,
                'format' => (int) $currency['format'],
                'decimals' => (bool) isset($currency['decimals']) ? $currency['decimals'] : true,
            ];
        }

        $order = new Order($params['id_order']);
        $this->context->smarty->assign([
            'ajaxEndpoint' => $this->context->link->getAdminLink('AdminModules', true) . '&configure=mollie&ajax=1&action=MollieOrderInfo',
            'transactionId' => $transaction['transaction_id'],
            'currencies' => $currencies,
            'tracking' => $shipmentService->getShipmentInformation($order->reference),
            'publicPath' => __PS_BASE_URI__ . 'modules/' . basename(__FILE__, '.php') . '/views/js/dist/',
            'webPackChunks' => \Mollie\Utility\UrlPathUtility::getWebpackChunks('app'),
            'errorDisplay' => Configuration::get(Mollie\Config\Config::MOLLIE_DISPLAY_ERRORS),
        ]);

        return $this->display(__FILE__, 'order_info.tpl');
    }

    /**
     * @param array $params
     *
     * @return array|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookPaymentOptions($params)
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return [];
        }

        $paymentOptions = [];

        /** @var PaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $this->getService(PaymentMethodRepositoryInterface::class);

        /** @var PaymentOptionHandlerInterface $paymentOptionsHandler */
        $paymentOptionsHandler = $this->getService(PaymentOptionHandlerInterface::class);

        /** @var PaymentMethodService $paymentMethodService */
        $paymentMethodService = $this->getService(PaymentMethodService::class);

        /** @var PrestaLoggerInterface $logger */
        $logger = $this->getService(PrestaLoggerInterface::class);

        $methods = $paymentMethodService->getMethodsForCheckout();

        foreach ($methods as $method) {
            /** @var MolPaymentMethod|null $paymentMethod */
            $paymentMethod = $paymentMethodRepository->findOneBy(['id_payment_method' => (int) $method['id_payment_method']]);

            if (!$paymentMethod) {
                continue;
            }
            $paymentMethod->method_name = $method['method_name'];

            try {
                $paymentOptions[] = $paymentOptionsHandler->handle($paymentMethod);
            } catch (Exception $exception) {
                // TODO handle payment fee exception and other exceptions with custom exception throw

                $logger->error($exception->getMessage());
            }
        }

        return $paymentOptions;
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayOrderConfirmation()
    {
        /** @var PaymentMethodRepositoryInterface $paymentMethodRepo */
        $paymentMethodRepo = $this->getService(PaymentMethodRepositoryInterface::class);
        $payment = $paymentMethodRepo->getPaymentBy('cart_id', (string) Tools::getValue('id_cart'));
        if (!$payment) {
            return '';
        }
        $isPaid = \Mollie\Api\Types\PaymentStatus::STATUS_PAID == $payment['bank_status'];
        $isAuthorized = \Mollie\Api\Types\PaymentStatus::STATUS_AUTHORIZED == $payment['bank_status'];
        if (($isPaid || $isAuthorized)) {
            $this->context->smarty->assign('okMessage', $this->l('Thank you. We received your payment.'));

            return $this->display(__FILE__, 'ok.tpl');
        }

        return '';
    }

    /**
     * @return array
     *
     * @since 3.3.0
     */
    public function displayAjaxMollieOrderInfo()
    {
        header('Content-Type: application/json;charset=UTF-8');

        /** @var MollieOrderInfoService $orderInfoService */
        $orderInfoService = $this->getService(MollieOrderInfoService::class);

        $input = @json_decode(Tools::file_get_contents('php://input'), true);

        return $orderInfoService->displayMollieOrderInfo($input);
    }

    /**
     * actionOrderStatusUpdate hook.
     *
     * @param array $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     *
     * @since 3.3.0
     */
    public function hookActionOrderStatusUpdate(array $params = [])
    {
        if (!isset($params['newOrderStatus'], $params['id_order'])) {
            return;
        }

        if ($params['newOrderStatus'] instanceof OrderState) {
            $orderStatus = $params['newOrderStatus'];
        } else {
            $orderStatus = new OrderState((int) $params['newOrderStatus']);
        }

        $order = new Order($params['id_order']);

        if (!Validate::isLoadedObject($orderStatus)) {
            return;
        }

        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $apiClient = $this->getApiClient($this->context->shop->id);

        if (!$apiClient) {
            return;
        }

        /** @var IsPaymentInformationAvailable $isPaymentInformationAvailable */
        $isPaymentInformationAvailable = $this->getService(IsPaymentInformationAvailable::class);

        if (!$isPaymentInformationAvailable->verify((int) $order->id)) {
            return;
        }

        /** @var ShipmentSenderHandlerInterface $shipmentSenderHandler */
        $shipmentSenderHandler = $this->getService(ShipmentSenderHandlerInterface::class);

        /** @var ExceptionService $exceptionService */
        $exceptionService = $this->getService(ExceptionService::class);

        /** @var PrestaLoggerInterface $logger */
        $logger = $this->getService(PrestaLoggerInterface::class);

        try {
            $shipmentSenderHandler->handleShipmentSender($apiClient, $order, $orderStatus);
        } catch (ShipmentCannotBeSentException $exception) {
            $logger->error($exceptionService->getErrorMessageForException(
                $exception,
                [],
                ['orderReference' => $order->reference]
            ));

            return;
        } catch (ApiException $exception) {
            $logger->error($exception->getMessage());

            return;
        }
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    public function hookActionEmailSendBefore($params)
    {
        if (!isset($params['cart']->id)) {
            return true;
        }

        $cart = new Cart($params['cart']->id);
        $orderId = Order::getOrderByCartId($cart->id);
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            return true;
        }

        if ($order->module !== $this->name) {
            return true;
        }

        /** @var OrderConfMailValidator $orderConfMailValidator */
        $orderConfMailValidator = $this->getService(OrderConfMailValidator::class);

        /** @var string $template */
        $template = $params['template'];

        if ('order_conf' === $template ||
            'account' === $template ||
            'backoffice_order' === $template ||
            'contact_form' === $template ||
            'credit_slip' === $template ||
            'in_transit' === $template ||
            'order_changed' === $template ||
            'order_merchant_comment' === $template ||
            'order_return_state' === $template ||
            'cheque' === $template ||
            'payment' === $template ||
            'preparation' === $template ||
            'shipped' === $template ||
            'order_canceled' === $template ||
            'payment_error' === $template ||
            'outofstock' === $template ||
            'bankwire' === $template ||
            'refund' === $template) {
            /** @var MolOrderPaymentFeeRepositoryInterface $molOrderPaymentFeeRepository */
            $molOrderPaymentFeeRepository = $this->getService(MolOrderPaymentFeeRepositoryInterface::class);

            /** @var ToolsAdapter $tools */
            $tools = $this->getService(ToolsAdapter::class);

            $orderCurrency = new Currency($order->id_currency);

            /** @var MolOrderPaymentFee|null $molOrderPaymentFee */
            $molOrderPaymentFee = $molOrderPaymentFeeRepository->findOneBy([
                'id_order' => (int) $order->id,
            ]);

            $feeTaxIncl = !empty($molOrderPaymentFee->fee_tax_incl) ? (float) $molOrderPaymentFee->fee_tax_incl : 0.00;

            if (PsVersionUtility::isPsVersionLowerThan(_PS_VERSION_, '1.7.6.0')) {
                $orderFee = $tools->displayPrice(
                    $feeTaxIncl,
                    $orderCurrency
                );
            } else {
                /**
                 * NOTE: Locale in context is set at init() method but in this case init() doesn't always get executed first.
                 */
                /** @var Repository $localeRepo */
                $localeRepo = $this->get('prestashop.core.localization.locale.repository');

                /**
                 * NOTE: context language is set based on customer/employee context
                 */
                $locale = $localeRepo->getLocale($this->context->language->getLocale());

                $orderFee = $locale->formatPrice(
                    $feeTaxIncl,
                    $orderCurrency->iso_code
                );
            }

            $params['templateVars']['{payment_fee}'] = $orderFee;
        }

        if ('order_conf' === $template) {
            return $orderConfMailValidator->validate((int) $order->current_state);
        }

        return true;
    }

    public function hookDisplayPDFInvoice($params): string
    {
        if (!isset($params['object'])) {
            return '';
        }

        if (!$params['object'] instanceof OrderInvoice) {
            return '';
        }

        /** @var InvoicePdfTemplateBuilder $invoiceTemplateBuilder */
        $invoiceTemplateBuilder = $this->getService(InvoicePdfTemplateBuilder::class);

        $locale = null;

        if (PsVersionUtility::isPsVersionHigherThen(_PS_VERSION_, '1.7.6.0')) {
            /** @var Repository $localeRepo */
            $localeRepo = $this->get('prestashop.core.localization.locale.repository');

            /**
             * NOTE: context language is set based on customer/employee context
             */
            $locale = $localeRepo->getLocale($this->context->language->getLocale());
        }

        $templateParams = $invoiceTemplateBuilder
            ->setOrder($params['object']->getOrder())
            ->setLocale($locale)
            ->buildParams();

        if (empty($templateParams)) {
            return '';
        }

        $this->context->smarty->assign($templateParams);

        return $this->context->smarty->fetch(
            $this->getLocalPath() . 'views/templates/admin/invoice_fee.tpl'
        );
    }

    /**
     * @return array
     */
    public function getTabs()
    {
        return [
            [
                'name' => $this->name,
                'class_name' => self::ADMIN_MOLLIE_CONTROLLER,
                'ParentClassName' => 'AdminParentShipping',
                'parent' => 'AdminParentShipping',
            ],
            [
                'name' => $this->l('AJAX', __CLASS__),
                'class_name' => self::ADMIN_MOLLIE_AJAX_CONTROLLER,
                'ParentClassName' => self::ADMIN_MOLLIE_CONTROLLER,
                'parent' => self::ADMIN_MOLLIE_CONTROLLER,
                'module_tab' => true,
                'visible' => false,
            ],
        ];
    }

    public function hookActionAdminOrdersListingFieldsModifier($params)
    {
        if (isset($params['select'])) {
            $params['select'] = rtrim($params['select'], ' ,') . ' ,mol.`transaction_id`';
        }
        if (isset($params['join'])) {
            $params['join'] .= ' LEFT JOIN `' . _DB_PREFIX_ . 'mollie_payments` mol ON mol.`cart_id` = a.`id_cart` AND mol.order_id > 0';
        }
        $params['fields']['order_id'] = [
            'title' => $this->l('Payment link'),
            'align' => 'text-center',
            'class' => 'fixed-width-xs',
            'orderby' => false,
            'search' => false,
            'remove_onclick' => true,
            'callback_object' => 'mollie',
            'callback' => 'resendOrderPaymentLink',
        ];
    }

    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        if (\Configuration::get(\Mollie\Config\Config::MOLLIE_SHOW_RESEND_PAYMENT_LINK) === \Mollie\Config\Config::HIDE_RESENT_LINK) {
            return;
        }

        /** @var OrderGridDefinitionModifier $orderGridDefinitionModifier */
        $orderGridDefinitionModifier = $this->getService(OrderGridDefinitionModifier::class);
        $gridDefinition = $params['definition'];

        $orderGridDefinitionModifier->modify($gridDefinition);
    }

    public function hookActionOrderGridQueryBuilderModifier(array $params)
    {
        /** @var OrderGridQueryModifier $orderGridQueryModifier */
        $orderGridQueryModifier = $this->getService(OrderGridQueryModifier::class);
        $searchQueryBuilder = $params['search_query_builder'];

        $orderGridQueryModifier->modify($searchQueryBuilder);
    }

    public function hookActionValidateOrder($params)
    {
        if (!isset($this->context->controller) || 'admin' !== $this->context->controller->controller_type) {
            return;
        }

        $apiClient = $this->getApiClient($this->context->shop->id);

        if (!$apiClient) {
            return;
        }

        //NOTE as mollie-email-send is only in manual order creation in backoffice this should work only when mollie payment is chosen.
        if (!empty(Tools::getValue('mollie-email-send')) &&
            $params['order']->module === $this->name
        ) {
            $cartId = $params['cart']->id;
            $totalPaid = strval($params['order']->total_paid);
            $currency = $params['currency']->iso_code;
            $customerKey = $params['customer']->secure_key;
            $orderReference = $params['order']->reference;
            $orderPayment = $params['order']->payment;
            $orderId = $params['order']->id;

            /** @var PaymentMethodService $paymentMethodService */
            $paymentMethodService = $this->getService(PaymentMethodService::class);
            $paymentMethodObj = new MolPaymentMethod();
            $paymentData = $paymentMethodService->getPaymentData(
                $totalPaid,
                $currency,
                '',
                null,
                $cartId,
                $customerKey,
                $paymentMethodObj,
                $orderReference
            );

            $newPayment = $apiClient->payments->create($paymentData->jsonSerialize());

            /** @var PaymentMethodRepositoryInterface $paymentMethodRepository */
            $paymentMethodRepository = $this->getService(PaymentMethodRepositoryInterface::class);
            $paymentMethodRepository->addOpenStatusPayment(
                $cartId,
                $orderPayment,
                $newPayment->id,
                $orderId,
                $orderReference
            );

            $sendMolliePaymentMail = Tools::getValue('mollie-email-send');
            if ('on' === $sendMolliePaymentMail) {
                /** @var MolliePaymentMailService $molliePaymentMailService */
                $molliePaymentMailService = $this->getService(MolliePaymentMailService::class);
                $molliePaymentMailService->sendSecondChanceMail($orderId);
            }
        }
    }

    public function hookActionObjectOrderPaymentAddAfter($params)
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $params['object'];

        /** @var PaymentMethodRepositoryInterface $paymentMethodRepo */
        $paymentMethodRepo = $this->getService(PaymentMethodRepositoryInterface::class);

        $orders = Order::getByReference($orderPayment->order_reference);
        /** @var Order $order */
        $order = $orders->getFirst();
        if (!Validate::isLoadedObject($order)) {
            return;
        }
        $mollieOrder = $paymentMethodRepo->getPaymentBy('cart_id', $order->id_cart);
        if (!$mollieOrder) {
            return;
        }
        $orderPayment->payment_method = Config::$methods[$mollieOrder['method']];
        $orderPayment->update();
    }

    public function hookDisplayProductActions($params)
    {
        if (PsVersionUtility::isPsVersionHigherThen(_PS_VERSION_, '1.7.6.0')) {
            return $this->display(__FILE__, 'views/templates/front/apple_pay_direct.tpl');
        }
    }

    public function hookDisplayExpressCheckout($params)
    {
        return $this->display(__FILE__, 'views/templates/front/apple_pay_direct.tpl');
    }

    public function hookDisplayProductAdditionalInfo()
    {
        if (!PsVersionUtility::isPsVersionHigherThen(_PS_VERSION_, '1.7.6.0')) {
            return $this->display(__FILE__, 'views/templates/front/apple_pay_direct.tpl');
        }
    }

    /**
     * @param int $orderId
     *
     * @return string|bool
     *
     * @throws PrestaShopDatabaseException
     */
    public static function resendOrderPaymentLink($orderId)
    {
        /** @var Mollie $module */
        $module = Module::getInstanceByName('mollie');
        /** @var PaymentMethodRepositoryInterface $molliePaymentRepo */
        $molliePaymentRepo = $module->getService(PaymentMethodRepositoryInterface::class);
        $molPayment = $molliePaymentRepo->getPaymentBy('cart_id', (string) Cart::getCartIdByOrderId($orderId));
        if (\Mollie\Utility\MollieStatusUtility::isPaymentFinished($molPayment['bank_status'])) {
            return false;
        }

        /** @var OrderListActionBuilder $orderListActionBuilder */
        $orderListActionBuilder = $module->getService(OrderListActionBuilder::class);

        return $orderListActionBuilder->buildOrderPaymentResendButton($module->smarty, $orderId);
    }

    public function updateApiKey($shopId = null)
    {
        $this->setApiKey($shopId);
    }

    private function setApiKey($shopId = null)
    {
        if ($this->api && $shopId === null) {
            return;
        }
        /** @var ModuleRepository $moduleRepository */
        $moduleRepository = $this->getService(ModuleRepository::class);
        $moduleDatabaseVersion = $moduleRepository->getModuleDatabaseVersion($this->name);
        $needsUpgrade = Tools::version_compare($this->version, $moduleDatabaseVersion, '>');
        if ($needsUpgrade) {
            return;
        }

        /** @var ApiKeyService $apiKeyService */
        $apiKeyService = $this->getService(ApiKeyService::class);

        $environment = (int) Configuration::get(Mollie\Config\Config::MOLLIE_ENVIRONMENT);
        $apiKeyConfig = \Mollie\Config\Config::ENVIRONMENT_LIVE === (int) $environment ?
            Mollie\Config\Config::MOLLIE_API_KEY : Mollie\Config\Config::MOLLIE_API_KEY_TEST;

        $apiKey = Configuration::get($apiKeyConfig, null, null, $shopId);

        if (!$apiKey) {
            return;
        }
        try {
            $this->api = $apiKeyService->setApiKey($apiKey, $this->version);
        } catch (\Mollie\Api\Exceptions\IncompatiblePlatform $e) {
            $errorHandler = \Mollie\Handler\ErrorHandler\ErrorHandler::getInstance();
            $errorHandler->handle($e, $e->getCode(), false);
            PrestaShopLogger::addLog(__METHOD__ . ' - System incompatible: ' . $e->getMessage(), Mollie\Config\Config::CRASH);
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            $errorHandler = \Mollie\Handler\ErrorHandler\ErrorHandler::getInstance();
            $errorHandler->handle($e, $e->getCode(), false);
            $this->warning = $this->l('Payment error:') . $e->getMessage();
            PrestaShopLogger::addLog(__METHOD__ . ' said: ' . $this->warning, Mollie\Config\Config::CRASH);
        } catch (\Exception $e) {
            $errorHandler = \Mollie\Handler\ErrorHandler\ErrorHandler::getInstance();
            $errorHandler->handle($e, $e->getCode(), false);
            PrestaShopLogger::addLog(__METHOD__ . ' - System incompatible: ' . $e->getMessage(), Mollie\Config\Config::CRASH);
        }
    }

    public function runUpgradeModule()
    {
        /** @var Segment $segment */
        $segment = $this->getService(Segment::class);

        $segment->setMessage('Mollie module upgrade');
        $segment->track();

        return parent::runUpgradeModule();
    }

    private function isPhpVersionCompliant()
    {
        return self::SUPPORTED_PHP_VERSION <= PHP_VERSION_ID;
    }
}
