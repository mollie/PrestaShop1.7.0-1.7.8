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

namespace Mollie\Service\EntityManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface EntityManagerInterface
{
    /**
     * @param \ObjectModel $model
     * @param string $unitOfWorkType - @see ObjectModelUnitOfWork
     * @param string|null $specificKey
     *
     * @return EntityManagerInterface
     */
    public function persist(
        \ObjectModel $model,
        string $unitOfWorkType,
        $specificKey = null
    ): EntityManagerInterface;

    /**
     * @return array<\ObjectModel>
     *
     * @throws \PrestaShopException
     */
    public function flush(): array;
}
