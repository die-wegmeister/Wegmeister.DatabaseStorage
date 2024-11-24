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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Flow\Persistence\Repository;
use Neos\Flow\Persistence\QueryInterface;
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

    /**
     * Delete all entries of a storage by its identifier and an optional date interval.
     *
     * @param string $storageIdentifier Storage identifier
     * @param \DateInterval|null $dateInterval Date interval
     * @return int
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     */
    public function deleteByStorageIdentifierAndDateInterval(string $storageIdentifier, \DateInterval $dateInterval = null): int
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
            $this->remove($entry);
            $count++;
        }

        return $count;
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
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $excludedIdentifiersList = empty($excludedIdentifiers) ? '' : implode(',', $excludedIdentifiers);
        $queryBuilder->select('n.storageidentifier')
            ->from(DatabaseStorage::class, 'n')
            ->distinct(true)
            ->where('n.storageidentifier NOT IN(:excluded)')
            ->setParameter('excluded', $excludedIdentifiersList);

        $result = $queryBuilder->getQuery()->getResult();
        return array_column($result, 'storageidentifier');
    }
}
