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

namespace Mollie\DTO\Object;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Company implements \JsonSerializable
{
    /** @var string */
    private $vatNumber;
    /** @var string */
    private $registrationNumber;

    /**
     * @return string
     */
    public function getVatNumber(): string
    {
        return $this->vatNumber;
    }

    /**
     * @param string $vatNumber
     *
     * @maps vatNumber
     */
    public function setVatNumber(string $vatNumber)
    {
        $this->vatNumber = $vatNumber;
    }

    /**
     * @return string
     */
    public function getRegistrationNumber(): string
    {
        return $this->registrationNumber;
    }

    /**
     * @param string $registrationNumber
     *
     * @maps registrationNumber
     */
    public function setRegistrationNumber(string $registrationNumber)
    {
        $this->registrationNumber = $registrationNumber;
    }

    public function jsonSerialize()
    {
        $json = [];
        $json['vatNumber'] = $this->getVatNumber();
        $json['registrationNumber'] = $this->getRegistrationNumber();

        return array_filter($json, static function ($val) {
            return $val !== null && $val !== '';
        });
    }
}
