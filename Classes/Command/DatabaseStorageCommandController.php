<?php

namespace Wegmeister\DatabaseStorage\Command;

use DateInterval;
use DateTime;
use Wegmeister\DatabaseStorage\Service\DatabaseStorageService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

#[Flow\Scope("singleton")]
class DatabaseStorageCommandController extends CommandController
{
    #[Flow\Inject]
    protected DatabaseStorageService $databaseStorageService;

    #[Flow\InjectConfiguration(path: "cleanup", package: "Wegmeister.DatabaseStorage")]
    protected ?array $storageCleanupConfiguration;

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

        $storageIdentifiers = array_keys($this->storageCleanupConfiguration);
        $results = $this->getCleanupResultsForStorageIdentifier($storageIdentifiers);
        $this->output->outputTable($results, ['storageIdentifier', 'messages'], 'Cleanup results');
    }

    /**
     * Deletes entries of all storages older than given date interval.
     * You can also skip the configured storages.
     */
    public function cleanupAllStoragesCommand(string $dateInterval, bool $removeFiles = false, bool $includeConfiguredStorages = false): void
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
            $dateIntervalFromString = new DateInterval($dateInterval);
        } catch (\Exception $exception) {
            $this->outputFormatted(
                'Invalid date interval value, please check the format (https://www.php.net/manual/de/class.dateinterval.php).'
            );
            return;
        }
        $daysToKeepData = $this->getDaysToKeepFromConfiguredInterval($dateIntervalFromString);

        // Get the list af all storage identifiers and filter the optional excluded storages
        $skippedStorages = [];
        if ($includeConfiguredStorages === false) {
            $skippedStorages = array_keys($this->storageCleanupConfiguration);
        }
        $storageIdentifiers = $this->databaseStorageService->getListOfStorageIdentifiers($skippedStorages);

        $results = $this->getCleanupResultsForStorageIdentifier($storageIdentifiers, $dateIntervalFromString, $daysToKeepData, $removeFiles);
        $this->output->outputTable($results, ['storageIdentifier', 'messages'], 'Cleanup results');
    }

    /**
     * Get the cleanup results for the given storage identifiers.
     * If no date interval is provided, the cleanup configuration will be used.
     */
    protected function getCleanupResultsForStorageIdentifier(array $storageIdentifiers, ?DateInterval $dateInterval = null, int $daysToKeepData = -1, $removeFiles = false): array
    {
        $results = [];
        foreach ($storageIdentifiers as $storageIdentifier) {
            $results[$storageIdentifier] = ['storageIdentifier' => $storageIdentifier, 'messages' => ''];
            $newDateInterval = $dateInterval;

            // use the storage cleanup configuration if no date interval is provided
            if ($newDateInterval === null) {
                // Check if the date interval is valid
                try {
                    $newDateInterval = $this->getDateIntervalFromConfiguration($storageIdentifier);
                } catch (\Exception $exception) {
                    $results[$storageIdentifier]['messages'] .= $exception->getMessage() . PHP_EOL;
                    continue;
                }

                // Check if we have entries for the storage identifier
                $daysToKeepData = $this->getDaysToKeepFromConfiguredInterval($newDateInterval);

                // Use removeFiles from configuration if not provided
                $removeFiles = filter_var(
                    $this->storageCleanupConfiguration[$storageIdentifier]['removeFiles'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
            }

            $amountOfEntries = $this->databaseStorageService->getAmountOfEntriesByStorageIdentifier($storageIdentifier);
            if ($amountOfEntries === 0) {
                $results[$storageIdentifier]['messages'] .= sprintf('No entries found in storage "%s".', $storageIdentifier) . PHP_EOL;
                continue;
            }

            // Cleanup the storage
            $results[$storageIdentifier]['messages'] .= vsprintf('Removing entries from storage "%s" older than %s days.', [$storageIdentifier, $daysToKeepData]) . PHP_EOL;
            $amountOfOutdatedEntries = $this->databaseStorageService->cleanupByStorageIdentifierAndDateInterval($storageIdentifier, $newDateInterval, $removeFiles);
            $results[$storageIdentifier]['messages'] .= vsprintf('Removed %s entries from storage "%s" (%s entries in total).', [$amountOfOutdatedEntries, $storageIdentifier, $amountOfEntries]);
        }

        return $results;
    }

    /**
     * Get the date interval from the configuration and ensure it is valid.
     */
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

    /**
     * Get the amount of days to keep from the configured date interval
     */
    protected function getDaysToKeepFromConfiguredInterval(DateInterval $dateInterval): int
    {
        $intervalDateTime = (new DateTime())->add($dateInterval);
        return date_diff($intervalDateTime, new DateTime('now'))->days;
    }
}
