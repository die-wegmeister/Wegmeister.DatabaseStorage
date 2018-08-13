<?php
/**
 * Repository to load database storage entries.
 *
 * This file is part of the Flow Framework Package "Wegmeister.DatabaseStorage".
 *
 * PHP version 7
 *
 * @category Repository
 * @package  Wegmeister\DatabaseStorage
 * @author   Benjamin Klix <benjamin.klix@die-wegmeister.com>
 * @license  https://github.com/die-wegmeister/Wegmeister.DatabaseStorage/blob/master/LICENSE GPL-3.0-or-later
 * @link     https://github.com/die-wegmeister/Wegmeister.DatabaseStorage
 */
namespace Wegmeister\DatabaseStorage\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use Neos\Flow\Persistence\QueryInterface;

/**
 * @Flow\Scope("singleton")
 */
class DatabaseStorageRepository extends Repository
{

    /**
     * Update default orderings.
     *
     * @var array
     */
    protected $defaultOrderings = [
        'storageidentifier' => QueryInterface::ORDER_ASCENDING,
        'datetime'   => QueryInterface::ORDER_DESCENDING
    ];

    /**
     * Currently used storage identifier.
     *
     * @var string
     */
    protected $currentIdentifier = false;

    /**
     * List of identifiers.
     *
     * @var array
     */
    protected $identifiers = [];


    /**
     * Find all identifiers.
     *
     * @return mixed
     */
    public function findStorageidentifiers()
    {
        if ($this->identifiers === []) {
            foreach ($this->findAll() as $item) {
                if ($this->currentIdentifier !== $item->getStorageidentifier()) {
                    $this->identifiers[] = $item->getStorageidentifier();
                    $this->currentIdentifier = $item->getStorageidentifier();
                }
            }
        }

        return $this->identifiers;
    }
}
