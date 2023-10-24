<?php

namespace Mollie\Tests\Integration\Application\CommandHandler;

use Mollie\Application\Command\RequestApplePayPaymentSession;
use Mollie\Application\CommandHandler\RequestApplePayPaymentSessionHandler;
use Mollie\Factory\ModuleFactory;
use Mollie\Tests\Integration\BaseTestCase;
use Mollie\Tests\Mocks\Service\ApiServiceMock;

class RequestApplePayPaymentSessionHandlerTest extends BaseTestCase
{
    /**
     * @dataProvider commandProvider
     */
    public function testHandle(RequestApplePayPaymentSession $command)
    {
        /** @var ModuleFactory $moduleFactory */
        $moduleFactory = $this->getService(ModuleFactory::class);
        $apiServiceMock = new ApiServiceMock();
        $handler = new RequestApplePayPaymentSessionHandler($moduleFactory, $apiServiceMock);
        $result = $handler->handle($command);

        $this->assertArrayHasKey('cartId', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertTrue($result['success']);
        if ($command->getCartId() !== 0) {
            $this->assertEquals($command->getCartId(), $result['cartId']);
        }
    }

    public function commandProvider()
    {
        return [
            'new cart case' => [
                    'command' => new RequestApplePayPaymentSession(
                        'test-validation-url',
                        1,
                        1,
                        0
                    ),
                ],
            'cart exist case' => [
                    'command' => new RequestApplePayPaymentSession(
                        'test-validation-url',
                        1,
                        1,
                        3
                    ),
                ],
        ];
    }
}
