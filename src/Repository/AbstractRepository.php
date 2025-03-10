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

namespace Mollie\Repository;

use ObjectModel;
use PrestaShopCollection;
use PrestaShopException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class AbstractRepository implements ReadOnlyRepositoryInterface
{
    /**
     * @var string
     */
    private $fullyClassifiedClassName;

    /**
     * @param string $fullyClassifiedClassName
     */
    public function __construct(string $fullyClassifiedClassName)
    {
        $this->fullyClassifiedClassName = $fullyClassifiedClassName;
    }

    public function findAll()
    {
        return new PrestaShopCollection($this->fullyClassifiedClassName);
    }

    /**
     * @param array $keyValueCriteria
     *
     * @return ObjectModel|null
     *
     * @throws PrestaShopException
     */
    public function findOneBy(array $keyValueCriteria)
    {
        $psCollection = new PrestaShopCollection($this->fullyClassifiedClassName);

        foreach ($keyValueCriteria as $field => $value) {
            $psCollection = $psCollection->where($field, '=', $value);
        }

        $first = $psCollection->getFirst();

        /* @phpstan-ignore-next-line */
        return false === $first ? null : $first;
    }

    /**
     * @param array $keyValueCriteria
     *
     * @return PrestaShopCollection|null
     *
     * @throws PrestaShopException
     */
    public function findAllBy(array $keyValueCriteria)
    {
        $psCollection = new PrestaShopCollection($this->fullyClassifiedClassName);

        foreach ($keyValueCriteria as $field => $value) {
            $psCollection = $psCollection->where($field, '=', $value);
        }

        $all = $psCollection->getAll();

        /* @phpstan-ignore-next-line */
        return false === $all ? null : $all;
    }
}
