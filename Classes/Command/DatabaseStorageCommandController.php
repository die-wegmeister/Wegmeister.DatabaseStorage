<?php

namespace Wegmeister\DatabaseStorage\Command;

use DateInterval;
use DateTime;
use Wegmeister\DatabaseStorage\Service\DatabaseStorageService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * @Flow\Scope("singleton")
 */
class DatabaseStorageCommandController extends CommandController
{
    /**
     * @var DatabaseStorageService
     * @Flow\Inject
     */
    protected $databaseStorageService;

    // TODO: set configurations and define path
    /**
     * @Flow\InjectConfiguration(package="Wegmeister.DatabaseStorage", path="cleanup")
     * @var array
     */
    protected $storageCleanupConfiguration;

    /**
     * Deletes entries of configured storages older than configured date interval
     */
    public function cleanUpConfiguredStoragesCommand(): void
    {
        foreach ($this->storageCleanupConfiguration as $storageIdentifier => $dateInterval) {
            $newDateInterval = new DateInterval($dateInterval['dateInterval']);
            $intervalDateTime = (new DateTime())->add($newDateInterval);
            $daysToKeepData = date_diff($intervalDateTime, new DateTime('now'))->days;

            $this->outputLine('Removing entries from storage "%s" older than %s days...', [$storageIdentifier, $daysToKeepData]);
            $outdatedEntries = 0;
            foreach ($this->databaseStorageService->getEntriesForCleanup($storageIdentifier) as $entry) {
                if (date_diff($entry->getDateTime(), new DateTime('now'))->days >= $daysToKeepData) {
                    $this->databaseStorageService->deleteEntry($entry);
                    $outdatedEntries++;
                }
            }
            $this->outputLine('Removed %s entries from storage "%s".', [$outdatedEntries, $storageIdentifier]);
        }
    }
}
