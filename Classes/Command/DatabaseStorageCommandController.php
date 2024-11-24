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
    protected DatabaseStorageService $databaseStorageService;

    /**
     * @Flow\InjectConfiguration(package="Wegmeister.DatabaseStorage", path="cleanup")
     * @var array
     */
    protected array $storageCleanupConfiguration;

    /**
     * Deletes entries of configured storages older than configured date interval
     */
    public function cleanUpConfiguredStoragesCommand(): void
    {
        $this->outputFormatted('<b>Cleanup of configured storages</b>');
        $this->outputLine('');

        if (empty($this->storageCleanupConfiguration)) {
            $this->outputFormatted('No cleanup configuration found.', [], 4);
            $this->outputFormatted('Please configure the cleanup for Wegmeister.DatabaseStorage in Settings.yaml.', [], 4);
            return;
        }

        $results = [];
        foreach ($this->storageCleanupConfiguration as $storageIdentifier => $storageCleanupConfiguration) {
            $results[$storageIdentifier] = ['storageIdentifier' => $storageIdentifier, 'messages' => ''];

            // Check if the date interval is valid
            try {
                $newDateInterval = $this->getDateIntervalFromConfiguration($storageIdentifier);
            } catch (\Exception $exception) {
                $results[$storageIdentifier]['messages'] .= $exception->getMessage() . PHP_EOL;
                continue;
            }

            // Check if we have entries for the storage identifier
            $daysToKeepData = $this->getDaysToKeepFromConfiguredInterval($newDateInterval);
            $amountOfEntries = $this->databaseStorageService->getAmountOfEntriesByStorageIdentifier($storageIdentifier);
            if ($amountOfEntries === 0) {
                $results[$storageIdentifier]['messages'] .= sprintf('No entries found in storage "%s".', $storageIdentifier) . PHP_EOL;
                continue;
            }

            // Cleanup the storage
            $results[$storageIdentifier]['messages'] .= vsprintf('Removing entries from storage "%s" older than %s days...', [$storageIdentifier, $daysToKeepData]) . PHP_EOL;
            $amountOfOutdatedEntries = $this->databaseStorageService->cleanupByStorageIdentifierAndDateInterval($storageIdentifier, $newDateInterval);
            $results[$storageIdentifier]['messages'] .= vsprintf('Removed %s entries from storage "%s" (%s entries in total).', [$amountOfOutdatedEntries, $storageIdentifier, $amountOfEntries]);
        }

        $this->output->outputTable($results, ['storageIdentifier', 'messages'], 'Cleanup results');
    }

    /**
     * Deletes entries of all storages older than given date interval.
     * You can also skip the configured storages.
     *
     * @param string $dateInterval
     * @param bool $skipConfiguredStorages
     */
    public function cleanupAllStoragesCommand(string $dateInterval, $skipConfiguredStorages = true): void
    {
        $this->outputFormatted('<b>Cleanup of all storages</b>');
        $this->outputLine('');

        // Check if the date interval is not empty and output an error message
        if (empty($dateInterval)) {
            $this->outputFormatted('Please provide a date interval.', [], 4);
            $this->outputFormatted('Example: P1M (1 month), P1Y (1 year), P1D (1 day), P1W (1 week), P1Y1M1D (1 year, 1 month, 1 day)', [], 4);
            return;
        }

        // Check if the date interval is valid
        try {
            $dateIntervalFromString =  new DateInterval($dateInterval);
        } catch (\Exception $exception) {
            $this->outputFormatted(
                'Invalid date interval value, please check the format (https://www.php.net/manual/de/class.dateinterval.php).'
            );
            return;
        }
        $daysToKeepData = $this->getDaysToKeepFromConfiguredInterval($dateIntervalFromString);

        // Get the list af all storage identifiers and filter the optional excluded storages
        $skippedStorages = [];
        if ($skipConfiguredStorages) {
            $skippedStorages = array_keys($this->storageCleanupConfiguration);
        }
        $storageIdentifiers = $this->databaseStorageService->getListOfStorageIdentifiers($skippedStorages);

        $results = [];
        foreach ($storageIdentifiers as $storageIdentifier) {
            $results[$storageIdentifier] = ['storageIdentifier' => $storageIdentifier, 'messages' => ''];

            // Check if we have entries for the storage identifier
            $amountOfEntries = $this->databaseStorageService->getAmountOfEntriesByStorageIdentifier($storageIdentifier);
            if ($amountOfEntries === 0) {
                $results[$storageIdentifier]['messages'] .= sprintf('No entries found in storage "%s".', $storageIdentifier) . PHP_EOL;
                continue;
            }

            // Cleanup the storage
            $results[$storageIdentifier]['messages'] .= vsprintf('Removing entries from storage "%s" older than %s days...', [$storageIdentifier, $daysToKeepData]) . PHP_EOL;
            $amountOfOutdatedEntries = $this->databaseStorageService->cleanupByStorageIdentifierAndDateInterval($storageIdentifier, $dateIntervalFromString);
            $results[$storageIdentifier]['messages'] .= vsprintf('Removed %s entries from storage "%s" (%s entries in total).', [$amountOfOutdatedEntries, $storageIdentifier, $amountOfEntries]);
        }

        $this->output->outputTable($results, ['storageIdentifier', 'messages'], 'Cleanup results');
    }

    protected function getDateIntervalFromConfiguration(string $storageIdentifier): DateInterval
    {
        $storageCleanupConfiguration = $this->storageCleanupConfiguration[$storageIdentifier] ?? null;

        if (!isset($storageCleanupConfiguration['dateInterval'])) {
            $errorMessage = vsprintf(
                'No date interval configuration for storage "%s" has been found.',
                [$storageIdentifier]
            );
            throw new \InvalidArgumentException($errorMessage, 1732462801);
        }

        try {
            return new DateInterval($storageCleanupConfiguration['dateInterval']);
        } catch (\Exception $exception) {
            $errorMessage = vsprintf(
                'Invalid date interval configuration for storage "%s".',
                [$storageIdentifier]
            );
            throw new \InvalidArgumentException($errorMessage, 1732462753);
        }
    }

    protected function getDaysToKeepFromConfiguredInterval(DateInterval $dateInterval): int
    {
        $intervalDateTime = (new DateTime())->add($dateInterval);
        return date_diff($intervalDateTime, new DateTime('now'))->days;
    }
}
