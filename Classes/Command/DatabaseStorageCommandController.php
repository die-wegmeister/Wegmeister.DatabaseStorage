<?php

namespace Wegmeister\DatabaseStorage\Command;

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
     * @Flow\InjectConfiguration(package="Vendor.Package", path="databaseStorageCleanup.storageCleanupConfiguration")
     * @var array
     */
    protected $storageCleanupConfiguration;

    /**
     * Deletes entries of honorary members, older than 6 months
     */
    public function cleanUpConfiguredStoragesCommand(): void
    {
        foreach ($this->storageCleanupConfiguration as $storageIdentifier => $daysToKeep) {
            $this->outputLine('Removing entries from storage "%s" older than %s days...', [$storageIdentifier, $daysToKeep]);
            $outdatedEntries = 0;
            foreach ($this->databaseStorageService->getEntriesForCleanup($storageIdentifier) as $entry) {
                if (date_diff($entry->getDateTime(), new DateTime('now'))->days >= $daysToKeep) {
                    $this->databaseStorageService->deleteEntry($entry);
                    $outdatedEntries++;
                }
            }
            $this->outputLine('Removed %s entries from storage "%s".', [$outdatedEntries, $storageIdentifier]);
        }
    }
}