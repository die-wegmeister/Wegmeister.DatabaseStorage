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

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Flow\Persistence\Repository;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;

/**
 * @Flow\Scope("singleton")
 */
class DatabaseStorageRepository extends Repository
{
    /**
     * Doctrine's Entity Manager.
     *
     * @Flow\Inject
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * Update default orderings.
     *
     * @var array
     */
    protected $defaultOrderings = [
        'storageidentifier' => QueryInterface::ORDER_ASCENDING,
        'datetime' => QueryInterface::ORDER_DESCENDING
    ];

    /**
     * List of identifiers.
     *
     * @var ?array
     */
    protected $identifiers = null;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;


    /**
     * Find all identifiers.
     *
     * @return mixed
     */
    public function findStorageidentifiers()
    {
        if ($this->identifiers === null) {
            $this->identifiers = $this->getStorageIdentifiers();
        }

        return $this->identifiers;
    }

    /**
     * Delete all entries of a storage by its identifier and an optional date interval.
     *
     * @param string $storageIdentifier Storage identifier
     * @param \DateInterval|null $dateInterval Date interval
     * @param bool $removeAttachedResource
     * @return int
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     */
    public function deleteByStorageIdentifierAndDateInterval(string $storageIdentifier, \DateInterval $dateInterval = null, $removeAttachedResource = false): int
    {
        $query = $this->createQuery();
        $constraints = [
            $query->equals('storageidentifier', $storageIdentifier)
        ];

        if ($dateInterval !== null) {
            $currentDate = new \DateTime();
            $newDate = $currentDate->sub($dateInterval);
            $constraints[] = $query->lessThan('datetime', $newDate->format('Y-m-d H:i:s'));
        }

        $query->matching($query->logicalAnd($constraints));
        $entries = $query->execute();
        $count = 0;
        foreach ($entries as $entry) {
            if ($removeAttachedResource) {
                $this->removeResourceFromEntry($entry);
            }
            $this->remove($entry);
            $count++;
        }

        return $count;
    }

    /**
     * Checks if the given storage entry has a property with a resource.
     * If a resource property is found, the resource will be deleted.
     *
     * @param DatabaseStorage $entry
     * @return void
     */
    protected function removeResourceFromEntry(DatabaseStorage $entry): void
    {
        try {
            foreach ($entry->getProperties() as $property) {
                if (!$property instanceof PersistentResource) {
                    continue;
                }

                $this->resourceManager->deleteResource($property);
            }
        } catch (\Doctrine\ORM\EntityNotFoundException $exception) {
            // Maybe the entry is already deleted and therefore not found
            return;
        }
    }

    /**
     * Get the list of all storage identifiers. Optionally exclude some.
     * For performance reasons, this method does not use the ORM.
     *
     * @param array $excludedIdentifiers
     * @return array
     */
    public function getStorageIdentifiers(array $excludedIdentifiers = []): array
    {
        if (empty($excludedIdentifiers)) {
            // If no excluded identifiers are given, we need to add an empty string to the array
            $excludedIdentifiers[] = '';
        }
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('n.storageidentifier')
            ->from(DatabaseStorage::class, 'n')
            ->distinct(true)
            ->where('n.storageidentifier NOT IN(:excluded)')
            ->setParameter('excluded', $excludedIdentifiers, Connection::PARAM_STR_ARRAY);

        $result = $queryBuilder->getQuery()->getResult();
        return array_column($result, 'storageidentifier');
    }

    /**
     * Checks if there are entries for given storage identifier.
     *
     * @param string $storageIdentifier
     * @return int
     */
    public function getAmountOfEntriesByStorageIdentifier(string $storageIdentifier): int
    {
        $query = $this->createQuery();
        $constraints = [];
        $constraints[] = $query->equals('storageidentifier', $storageIdentifier);
        $query->matching(
            $query->logicalAnd(
                $constraints
            )
        );

        return $query->count();
    }
}
