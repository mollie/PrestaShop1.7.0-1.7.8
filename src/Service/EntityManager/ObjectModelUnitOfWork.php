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

/** In memory entity manager object model unit of work */
class ObjectModelUnitOfWork
{
    const UNIT_OF_WORK_SAVE = 'UNIT_OF_WORK_SAVE';
    const UNIT_OF_WORK_DELETE = 'UNIT_OF_WORK_DELETE';

    private $work = [];

    /**
     * @param \ObjectModel $objectModel
     * @param string $unitOfWorkType
     * @param string|null $specificKey
     *
     * @return void
     */
    public function setWork(\ObjectModel $objectModel, string $unitOfWorkType, $specificKey)
    {
        $work = [
            'unit_of_work_type' => $unitOfWorkType,
            'object' => $objectModel,
        ];

        if (!is_null($specificKey)) {
            $this->work[$specificKey] = $work;
        } else {
            $this->work[] = $work;
        }
    }

    /**
     * @return array<string, \ObjectModel>
     */
    public function getWork(): array
    {
        return $this->work;
    }

    public function clearWork()
    {
        $this->work = [];
    }
}
