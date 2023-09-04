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

namespace Mollie\Builder\Content;

use Mollie;
use Mollie\Builder\TemplateBuilderInterface;
use Mollie\Factory\ModuleFactory;

class LogoInfoBlock implements TemplateBuilderInterface
{
    /**
     * @var Mollie
     */
    private $module;

    public function __construct(ModuleFactory $moduleFactory)
    {
        $this->module = $moduleFactory->getModule();
    }

    /**
     * {@inheritDoc}
     */
    public function buildParams()
    {
        return [
            'logo_url' => $this->module->getPathUri() . 'views/img/mollie_logo.png',
        ];
    }
}
