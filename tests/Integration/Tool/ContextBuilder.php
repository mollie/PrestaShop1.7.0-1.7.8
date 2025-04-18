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

namespace Mollie\Tests\Integration\Tool;

//NOTE need to handle employee id_lang and shop context by these functions as it changes stock and requires these values to not crash.

use Configuration;
use Context;
use Country;
use Currency;
use Employee;
use Language;
use Shop;

class ContextBuilder
{
    public function setDefaults()
    {
        $this->setCurrency(new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT')));  //TODO maybe a factory.
        $this->setEmployee(new Employee(1));  //TODO maybe a factory.
        $this->setLanguage(new Language(1));  //TODO maybe a factory.
        $this->setShop(new Shop(1));  //TODO maybe a factory.
    }

    public function setEmployee(Employee $employee)
    {
        Context::getContext()->employee = $employee;
        \Cache::store('isLoggedBack' . $employee->id, true);

        return $this;
    }

    public function setCurrency(Currency $currency)
    {
        Context::getContext()->currency = $currency;
        Configuration::set('PS_CURRENCY_DEFAULT', Context::getContext()->currency->id);

        return $this;
    }

    public function setCountry(Country $country)
    {
        Context::getContext()->country = $country;
        Configuration::set('PS_COUNTRY_DEFAULT', Context::getContext()->country->id);

        return $this;
    }

    public function setLanguage(Language $language)
    {
        Context::getContext()->language = $language;

        return $this;
    }

    public function setShop(Shop $shop)
    {
        Context::getContext()->shop = $shop;

        return $this;
    }

    public function setCart(\Cart $cart)
    {
        Context::getContext()->cart = $cart;

        return $this;
    }

    public function getContext()
    {
        return Context::getContext();
    }
}
