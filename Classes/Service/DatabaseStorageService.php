<?php

/**
 * This file is part of the Flow Framework Package "Wegmeister.DatabaseStorage".
 *
 * @category Controller
 * @package  Wegmeister\DatabaseStorage
 * @author   Benjamin Klix <benjamin.klix@die-wegmeister.com>
 * @license  https://github.com/die-wegmeister/Wegmeister.DatabaseStorage/blob/master/LICENSE GPL-3.0-or-later
 * @link     https://github.com/die-wegmeister/Wegmeister.DatabaseStorage
 */

namespace Wegmeister\DatabaseStorage\Service;

use Doctrine\ORM\EntityNotFoundException;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Neos\Domain\Service\SiteService;
use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;

/**
 * @Flow\Scope("singleton")
 */
class DatabaseStorageService
{
    /**
     * @var DatabaseStorageRepository
     * @Flow\Inject
     */
    protected $databaseStorageRepository;

    /**
     * @var array
     */
    protected $formElementsNodeData;

    /**
     * @var string
     */
    protected $formStorageIdentifier;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="nodeTypesIgnoredInFinisher", package="Wegmeister.DatabaseStorage")
     */
    protected $nodeTypesIgnoredInFinisher;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="nodeTypesIgnoredInExport", package="Wegmeister.DatabaseStorage")
     */
    protected $nodeTypesIgnoredInExport;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="datetimeFormat", package="Wegmeister.DatabaseStorage")
     */
    protected $datetimeFormat;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="contentDimensions", package="Neos.ContentRepository")
     */
    protected $contentDimensions;

    /**
     * @var array
     */
    protected $preparedDimensions;

    /**
     * @Flow\Inject
     * @var ContextFactory
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    public function __construct(string $formStorageIdentifier = '')
    {
        $this->formStorageIdentifier = $formStorageIdentifier;
    }

    public function formElementIdentifierMustBeIgnoredInFinisher(string $formValueIdentifier): bool
    {
        $formElementData = $this->getFormElementDataByIdentifier($formValueIdentifier);
        if (!$formElementData) {
            // No Form definition found, so we cannot know if it should be ignored --> do nothing
            return false;
        }
        return in_array($formElementData['nodeTypeName'], $this->nodeTypesIgnoredInFinisher);
    }

    public function nodeTypeMustBeIgnoredInExport($nodeTypeName): bool
    {
        return in_array($nodeTypeName, $this->nodeTypesIgnoredInExport);
    }

    /**
     * Return the form element mapping for a Node-based form
     * Three possible values are looked up:
     * - The Node identifier
     * - The speaking identifier of the FormElement
     * - The label of the FormElement
     *
     * @param string $identifier
     * @return array|null
     */
    public function getFormElementDataByIdentifier(string $identifier): ?array
    {
        $formElementsNodeData = $this->getFormElementsNodeData();
        if (!$formElementsNodeData) {
            return null;
        }
        foreach ($formElementsNodeData as $formElementNodeData) {
            // Given identifier can be either the nodeIdentifier or the speakingIdentifier, so we must search for both
            if ($formElementNodeData['nodeIdentifier'] === $identifier) {
                return $formElementNodeData;
            }
            if ($formElementNodeData['speakingIdentifier'] === $identifier) {
                return $formElementNodeData;
            }
            if ($formElementNodeData['displayLabel'] === $identifier) {
                return $formElementNodeData;
            }
        }
        return null;
    }

    /**
     * Prepare configured dimensions for easier iteration
     * @return void
     */
    protected function prepareDimensions(): void
    {
        if ($this->preparedDimensions !== null) {
            // Dimensions are already prepared
            return;
        }

        if (empty($this->contentDimensions)) {
            // No dimensions configured
            $this->preparedDimensions = [];
            return;
        }

        $preparedDimensions = [];
        foreach ($this->contentDimensions as $identifier => $dimension) {
            // Move default preset to first position
            $dimensionPresets = array_merge(
                [$dimension['default'] => $dimension['presets'][$dimension['default']]],
                $dimension['presets'],
            );

            $preparedDimensions[$identifier] = [];
            foreach ($dimensionPresets as $targetDimension => $preset) {
                $preparedDimensions[$identifier][] = [
                    'dimensions' => $preset['values'],
                    'targetDimensions' => $targetDimension,
                ];
            }
        }

        $this->preparedDimensions = $this->createDimensionCombinations($preparedDimensions);
    }

    /**
     * Create all possible combinations of the configured dimensions.
     * Will be called recursively to create all possible combinations.
     *
     * @param array $inputArray The multidimensional array of the dimensions
     * @param array $keys The keys of the dimensions
     *
     * @return array
     */
    protected function createDimensionCombinations(array $inputArray, array $combinations = []): array
    {
        $result = [];

        // Fetch first key and its values
        $dimensionKey = array_key_first($inputArray);
        $dimensionValues = $inputArray[$dimensionKey];

        // Remove the first key from the array, to allow recursion
        unset($inputArray[$dimensionKey]);

        foreach ($dimensionValues as $dimensionValue) {
            // Add the current dimansion and its value to the combinations
            $combinations[$dimensionKey] = $dimensionValue;

            if (empty($inputArray)) {
                // No more dimensions to process, add the current combination to the result
                $result[] = $combinations;
            } else {
                // Recursively process the next dimension
                $result = array_merge($result, $this->createDimensionCombinations($inputArray, $combinations));
            }
        }

        return $result;
    }

    /**
     * If the Node-based form is still available, node data such as the "speaking" identifier, the label
     * are looked up to provide the best possible label and value matching for the export.
     *
     * @return array|null
     * @throws \Neos\ContentRepository\Exception\NodeException
     * @throws \Neos\Eel\Exception
     */
    protected function getFormElementsNodeData(?array $dimensions = []): ?array
    {
        if (!empty($this->formElementsNodeData)) {
            // First-level cache
            return $this->formElementsNodeData;
        }

        $this->prepareDimensions();
        if (empty($dimensions)) {
            $dimensions = reset($this->preparedDimensions);
        }

        $contextProperties = [
            'workspaceName' => 'live',
            'invisibleContentShown' => true,
            'removedContentShown' => true,
            'inaccessibleContentShown' => false,
        ];

        if (!empty($dimensions)) {
            $contextProperties['dimensions'] = [];
            $contextProperties['targetDimensions'] = [];

            foreach ($dimensions as $dimension => $dimensionPreset) {
                $contextProperties['dimensions'][$dimension] = $dimensionPreset['dimensions'];
                $contextProperties['targetDimensions'][$dimension] = $dimensionPreset['targetDimensions'];
            }
        }

        $context = $this->contextFactory->create(
            $contextProperties
        );

        // Find the finisher belonging to the formStorageIdentifier
        $q = new FlowQuery([$context->getNode(SiteService::SITES_ROOT_PATH)]);
        $finisherNodes = $q->find(
            "[instanceof Wegmeister.DatabaseStorage:DatabaseStorageFinisher][identifier='" . $this->formStorageIdentifier . "']"
        )->get();

        if (count($finisherNodes) !== 1) {
            // None or more than one Finisher with the same identifier --> could be a Fusion or YAML form or ambiguous --> return
            $nextDimensions = next($this->preparedDimensions);

            if ($nextDimensions !== false) {
                return $this->getFormElementsNodeData($nextDimensions);
            }

            return null;
        }

        // Find the NodeBasedForm owning the Finisher
        $q = new FlowQuery([$finisherNodes[0]]);
        $formNode = $q->parents('[instanceof Neos.Form.Builder:NodeBasedForm]')->get(0);
        if (!$formNode instanceof NodeInterface) {
            // No NodeBasedForm found, return
            $nextDimensions = next($this->preparedDimensions);

            if ($nextDimensions !== false) {
                return $this->getFormElementsNodeData($nextDimensions);
            }

            return null;
        }

        // Find all FormElements belonging to the Form
        $q = new FlowQuery([$formNode]);
        $formElements = $q->find('[instanceof Neos.Form.Builder:FormElement]')->get();

        if (empty($formElements)) {
            // No FormElements found, return
            $nextDimensions = next($this->preparedDimensions);

            if ($nextDimensions !== false) {
                return $this->getFormElementsNodeData($nextDimensions);
            }

            return null;
        }

        $mapping = [];

        /** @var NodeInterface $formElement */
        foreach ($formElements as $formElement) {
            // UUID of the FormElement node
            $nodeIdentifier = (string)$formElement->getNodeAggregateIdentifier();
            // Given identifier of the FormElement
            $speakingIdentifier = $formElement->getProperty('identifier');
            // Label of the FormElement
            $label = $formElement->getProperty('label');
            // "Best available" label
            $displayLabel = $label ?: $speakingIdentifier ?: $nodeIdentifier;

            $mapping[] = [
                'nodeTypeName' => $formElement->getNodeType()->getName(),
                'nodeIdentifier' => $nodeIdentifier,
                'speakingIdentifier' => $speakingIdentifier,
                'label' => $label,
                'displayLabel' => $displayLabel,
            ];
        }

        $this->formElementsNodeData = $mapping;
        return $mapping;
    }

    /**
     * Get field labels of all entries to allow exporting all fields added/removed/changed over time
     *
     * @param QueryResultInterface $entries
     * @return array
     */
    public function getFormElementLabels(QueryResultInterface $entries): array
    {
        $mapping = [];

        /** @var DatabaseStorage $entry */
        foreach ($entries as $entry) {
            foreach ($entry->getProperties() as $key => $value) {
                $formElementMapping = $this->getFormElementDataByIdentifier($key);
                if (!$formElementMapping) {
                    /*
                     * There is no mapping for one of the following reasons:
                     * - It is a Fusion-based form
                     * - It is a YAML-based form
                     * - The form could have been removed
                     * - The field could have been removed
                     * - The field identifier could have been renamed
                     * In this case, we use the key as fallback, meaning the field
                     * is labelled as stored in the entry, which is usually "speaking
                     * enough" at least for Fusion-based and YAML-based forms.
                     *
                     */
                    $mapping[$key] = $key;
                    continue;
                }
                if ($this->nodeTypeMustBeIgnoredInExport($formElementMapping['nodeTypeName'])) {
                    continue;
                }
                $mapping[$formElementMapping['displayLabel']] = $formElementMapping['displayLabel'];
            }
        }
        return $mapping;
    }

    /**
     * We check the given entry if there is a value for the given display label
     * The check is performed against the key, the nodeIdentifier and the speakingIdentifier
     *
     * @param DatabaseStorage $entry
     * @param string $formElementLabel
     * @return string
     */
    public function getValueFromEntryProperty(DatabaseStorage $entry, string $formElementLabel): string
    {

        // For Fusion- or YAML-based forms
        if (array_key_exists($formElementLabel, $entry->getProperties())) {
            return $this->getStringValue($entry->getProperties()[$formElementLabel]);
        }

        $formElementData = $this->getFormElementDataByIdentifier($formElementLabel);

        // No data for this field in this entry, it was probably removed
        if (!$formElementData) {
            return '';
        }

        // Key is node identifier
        if (array_key_exists($formElementData['nodeIdentifier'], $entry->getProperties())) {
            return $this->getStringValue($entry->getProperties()[$formElementData['nodeIdentifier']]);
        }

        // Key is speaking identifier
        if (array_key_exists($formElementData['speakingIdentifier'], $entry->getProperties())) {
            return $this->getStringValue($entry->getProperties()[$formElementData['speakingIdentifier']]);
        }

        // Really no data for this field
        return '';
    }

    /**
     * Internal function to replace value with a string for export / listing.
     *
     * @param mixed $value The database column value.
     * @param int $indent The level of indentation (for array values).
     *
     * @return string
     */
    protected function getStringValue($value, int $indent = 0): string
    {
        if ($value instanceof PersistentResource) {
            try {
                $resourceUri = $this->resourceManager->getPublicPersistentResourceUri($value);
            } catch (EntityNotFoundException $e) {
                return '';
            }
            return $resourceUri;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return ($value ? 'true' : 'false');
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }
        if (isset($value['dateFormat'], $value['date'])) {
            $timezone = null;
            if (isset($value['timezone'])) {
                $timezone = new \DateTimeZone($value['timezone']);
            }
            $dateTime = \DateTime::createFromFormat($value['dateFormat'], $value['date'], $timezone);
            return $dateTime->format($this->datetimeFormat);
        }
        if (is_array($value)) {
            foreach ($value as &$innerValue) {
                $innerValue = $this->getStringValue($innerValue, $indent + 1);
            }
            $prefix = str_repeat(' ', $indent * 2) . '- ';
            return sprintf(
                '%s%s',
                $prefix,
                implode("\r\n" . $prefix, $value)
            );
        }

        return '';
    }

    /**
     * Checks if there are entries for given storage identifier.
     *
     * @param string $storageIdentifier
     * @return int
     */
    public function getAmountOfEntriesByStorageIdentifier(string $storageIdentifier): int
    {
        $query = $this->databaseStorageRepository->createQuery();
        $constraints = [];
        $constraints[] = $query->equals('storageidentifier', $storageIdentifier);
        $query->matching(
            $query->logicalAnd(
                $constraints
            )
        );

        return $query->count();
    }

    /**
     * Deletes entries of a storage by its identifier and an optional date interval.
     *
     * @param string $storageIdentifier Storage identifier
     * @param \DateInterval $dateInterval Date interval
     * @return int
     */
    public function cleanupByStorageIdentifierAndDateInterval(string $storageIdentifier, \DateInterval $dateInterval): int
    {
        try {
            return $this->databaseStorageRepository->deleteByStorageIdentifierAndDateInterval($storageIdentifier, $dateInterval);
        } catch (IllegalObjectTypeException|InvalidQueryException $e) {
            return 0;
        }
    }
}
