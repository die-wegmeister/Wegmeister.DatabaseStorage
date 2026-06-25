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
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\Security\Exception\AccessDenied;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindDescendantNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\NodeType\NodeTypeCriteria;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\PropertyValue\Criteria\PropertyValueEquals;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\PropertyName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;

#[Flow\Scope("singleton")]
class DatabaseStorageService
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected ResourceManager $resourceManager;

    #[Flow\Inject]
    protected DatabaseStorageRepository $databaseStorageRepository;

    #[Flow\InjectConfiguration(path: "nodeTypesIgnoredInFinisher", package: "Wegmeister.DatabaseStorage")]
    protected array $nodeTypesIgnoredInFinisher;

    #[Flow\InjectConfiguration(path: "listPrefix", package: "Wegmeister.DatabaseStorage")]
    protected string $listPrefix;

    #[Flow\InjectConfiguration(path: "nodeTypesIgnoredInExport", package: "Wegmeister.DatabaseStorage")]
    protected array $nodeTypesIgnoredInExport;

    #[Flow\InjectConfiguration(path: "datetimeFormat", package: "Wegmeister.DatabaseStorage")]
    protected string $datetimeFormat;

    protected array $formElementsNodeData = [];

    protected string $formStorageIdentifier;

    protected ?array $preparedDimensions = null;

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
     */
    protected function prepareDimensions(ContentRepository $contentRepository): void
    {
        if ($this->preparedDimensions !== null) {
            // Dimensions are already prepared
            return;
        }

        $contentDimensions = $contentRepository->getContentDimensionSource()->getContentDimensionsOrderedByPriority();
        if (empty($contentDimensions)) {
            // No dimensions configured
            $this->preparedDimensions = [];
            return;
        }

        $preparedDimensions = [];
        foreach ($contentDimensions as $contentDimension) {
            $preparedDimensions[$contentDimension->id->value] = [];
            foreach ($contentDimension->values as $contentDimensionValue) {
                $preparedDimensions[$contentDimension->id->value][] = $contentDimensionValue->value;
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
            // Add the current dimension and its value to the combinations
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
     * @throws \Neos\Eel\Exception
     * @throws AccessDenied
     */
    protected function getFormElementsNodeData(?array $dimensions = []): ?array
    {
        if (!empty($this->formElementsNodeData)) {
            // First-level cache
            return $this->formElementsNodeData;
        }

        $contentRepository = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        $this->prepareDimensions($contentRepository);
        if (empty($dimensions)) {
            $dimensions = reset($this->preparedDimensions);
        }

        $contentGraph =  $contentRepository->getContentGraph(WorkspaceName::forLive());
        $sitesRootNodeNode = $contentGraph->findRootNodeAggregateByType(NodeTypeName::fromString('Neos.Neos:Sites'));
        $contentSubgraph = $contentGraph->getSubgraph(
            DimensionSpacePoint::fromArray($dimensions), VisibilityConstraints::createEmpty()
        );
        $finisherNodes = $contentSubgraph->findDescendantNodes(
            $sitesRootNodeNode->nodeAggregateId,
            FindDescendantNodesFilter::create(
                nodeTypes: NodeTypeCriteria::fromFilterString('Wegmeister.DatabaseStorage:DatabaseStorageFinisher'),
                propertyValue: PropertyValueEquals::create(PropertyName::fromString('storageIdentifier'), $this->formStorageIdentifier, true)
            )
        );

        if ($finisherNodes->count() !== 1) {
            // None or more than one Finisher with the same identifier --> could be a Fusion or YAML form or ambiguous --> return
            $nextDimensions = next($this->preparedDimensions);

            if ($nextDimensions !== false) {
                return $this->getFormElementsNodeData($nextDimensions);
            }

            return null;
        }

        // Find the NodeBasedForm owning the Finisher
        $formNode = $contentSubgraph->findClosestNode(
            $finisherNodes->first()->aggregateId,
            FindClosestNodeFilter::create(NodeTypeCriteria::fromFilterString('Neos.Form.Builder:NodeBasedForm'))
        );

        if (!$formNode instanceof \Neos\ContentRepository\Core\Projection\ContentGraph\Node) {
            // No NodeBasedForm found, return
            $nextDimensions = next($this->preparedDimensions);

            if ($nextDimensions !== false) {
                return $this->getFormElementsNodeData($nextDimensions);
            }
            return null;
        }

        // Find all FormElements belonging to the Form
        $formElements = $contentSubgraph->findDescendantNodes(
            $formNode->aggregateId,
            FindDescendantNodesFilter::create(
                nodeTypes: NodeTypeCriteria::fromFilterString('Neos.Form.Builder:FormElement')
            )
        );

        if ($formElements->isEmpty()) {
            // No FormElements found, return
            $nextDimensions = next($this->preparedDimensions);

            if ($nextDimensions !== false) {
                return $this->getFormElementsNodeData($nextDimensions);
            }
            return null;
        }

        $mapping = [];

        /** @var \Neos\ContentRepository\Core\Projection\ContentGraph\Node $formElement */
        foreach ($formElements as $formElement) {
            // UUID of the FormElement node
            $nodeIdentifier = $formElement->aggregateId->value;
            // Given identifier of the FormElement
            $speakingIdentifier = $formElement->getProperty('identifier');
            // Label of the FormElement
            $label = $formElement->getProperty('label');
            // "Best available" label
            $displayLabel = $label ?: $speakingIdentifier ?: $nodeIdentifier;

            $mapping[] = [
                'nodeTypeName' => $formElement->nodeTypeName->value,
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
            $lines = [];

            foreach ($value as $key => $innerValue) {
                $stringValue = $this->getStringValue($innerValue, $indent + 1);

                // If key is numeric, treat as flat array item
                if (is_int($key)) {
                    $lines[] = $stringValue;
                } else {
                    // Associative: keep key as part of output
                    $lines[] = sprintf('%s: %s', $key, $stringValue);
                }
            }

            $prefix = str_repeat(' ', $indent * 2) . $this->listPrefix . ' ';
            return $prefix . implode("\r\n" . $prefix, $lines);
        }

        return '';
    }

    /**
     * Checks if there are entries for given storage identifier.
     */
    public function getAmountOfEntriesByStorageIdentifier(string $storageIdentifier): int
    {
        return $this->databaseStorageRepository->getAmountOfEntriesByStorageIdentifier($storageIdentifier);
    }

    /**
     * Deletes entries of a storage by its identifier and an optional date interval.
     */
    public function cleanupByStorageIdentifierAndDateInterval(string $storageIdentifier, \DateInterval $dateInterval, bool $removeFiles = false): int
    {
        try {
            return $this->databaseStorageRepository->deleteByStorageIdentifierAndDateInterval($storageIdentifier, $dateInterval, $removeFiles);
        } catch (IllegalObjectTypeException|InvalidQueryException $e) {
            return 0;
        }
    }

    /**
     * Get a list of all storage identifiers
     */
    public function getListOfStorageIdentifiers(array $excludedStorageIdentifiers = []): array
    {
        return $this->databaseStorageRepository->getStorageIdentifiers($excludedStorageIdentifiers);
    }
}
