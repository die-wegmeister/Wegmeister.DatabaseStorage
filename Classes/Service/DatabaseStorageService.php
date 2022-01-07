<?php

/**
 * This file is part of the Flow Framework Package "Wegmeister.DatabaseStorage".
 *
 * PHP version 7
 *
 * @category Controller
 * @package  Wegmeister\DatabaseStorage
 * @author   Benjamin Klix <benjamin.klix@die-wegmeister.com>
 * @license  https://github.com/die-wegmeister/Wegmeister.DatabaseStorage/blob/master/LICENSE GPL-3.0-or-later
 * @link     https://github.com/die-wegmeister/Wegmeister.DatabaseStorage
 */

namespace Wegmeister\DatabaseStorage\Service;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactory;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Neos\Domain\Service\SiteService;
use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;

/**
 * @Flow\Scope("singleton")
 */
class DatabaseStorageService
{

    /**
     * @var array
     */
    protected $formElementMapping;

    /**
     * @var string
     */
    protected $formStorageIdentifier;

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

    public function nodeTypeMustBeIgnoredInExport($nodeTypeName): bool
    {
        return in_array($nodeTypeName, $this->nodeTypesIgnoredInExport);
    }

    public function getFormElementMappingByIdentifier(string $formElementIdentifier): ?array
    {
        $formElementsMapping = $this->getFormElementsMapping();
        if (!$formElementsMapping) {
            return null;
        }
        return $formElementsMapping[$formElementIdentifier] ?? null;
    }

    protected function getFormElementsMapping(): ?array
    {
        if (!empty($this->formElementMapping)) {
            return $this->formElementMapping;
        }
        $context = $this->contextFactory->create(
            [
                'workspaceName' => 'live',
                'invisibleContentShown' => true,
                'removedContentShown' => true,
                'inaccessibleContentShown' => false
            ]
        );

        // Find the finisher belonging to the formStorageIdentifier
        $q = new FlowQuery([$context->getNode(SiteService::SITES_ROOT_PATH)]);
        $finisherNodes = $q->find(
            "[instanceof Wegmeister.DatabaseStorage:DatabaseStorageFinisher][identifier='" . $this->formStorageIdentifier . "']"
        )->get();
        if (count($finisherNodes) !== 1) {
            // None or more than one Finisher with the same identifier --> ambiguous, return
            return null;
        }

        // Find the NodeBasedForm owning the Finisher
        $q = new FlowQuery([$finisherNodes[0]]);
        $formNode = $q->parents('[instanceof Neos.Form.Builder:NodeBasedForm]')->get(0);
        if (!$formNode instanceof NodeInterface) {
            // No NodeBasedForm found, return
            return null;
        }

        // Find all FormElements belonging to the Form
        $q = new FlowQuery([$formNode]);
        $formElements = $q->find('[instanceof Neos.Form.Builder:FormElement]')->get();

        if (empty($formElements)) {
            // No FormElements found, return
            return null;
        }

        $mapping = [];

        /** @var NodeInterface $formElement */
        foreach ($formElements as $formElement) {
            // If no (speaking) identifier property is set, fall back to Node identifier
            $formElementIdentifier = !empty($formElement->getProperty('identifier')) ? $formElement->getProperty(
                'identifier'
            ) : (string)$formElement->getNodeAggregateIdentifier();
            $mapping[$formElementIdentifier] = [
                'nodeTypeName' => $formElement->getNodeType()->getName(),
                'label' => $formElement->getProperty('label')
            ];
        }
        $this->formElementMapping = $mapping;
        return $mapping;
    }

    /**
     * Get field titles of all entries to allow the export of all fields added/removed over time
     *
     * @param QueryResultInterface $entries
     * @return array
     */
    public function getFormElementIdentifierToLabelMapping(QueryResultInterface $entries): array
    {
        $mapping = [];

        /** @var DatabaseStorage $entry */
        foreach ($entries as $entry) {
            foreach ($entry->getProperties() as $formElementIdentifier => $value) {
                $formElementMapping = $this->getFormElementMappingByIdentifier($formElementIdentifier);
                if (!$formElementMapping) {
                    /*
                     * We cannot assume that the Mapping is available:
                     * - The form could have been deleted
                     * - The field could have been removed
                     * - The field identifier could have been renamed
                     * In this case, we use the identifier as fallback
                     */
                    $mapping[$formElementIdentifier] = $formElementIdentifier;
                    continue;
                }
                if ($this->nodeTypeMustBeIgnoredInExport($formElementMapping['nodeTypeName'])) {
                    continue;
                }
                $mapping[$formElementIdentifier] = $formElementMapping['label'];
            }
        }
        return $mapping;
    }

    public function getValueFromEntryProperty(DatabaseStorage $entry, string $formElementIdentifier): string
    {
        if (!array_key_exists($formElementIdentifier, $entry->getProperties())) {
            return '-';
        }

        return $this->getStringValue($entry->getProperties()[$formElementIdentifier]);
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
            return $this->resourceManager->getPublicPersistentResourceUri($value) ?: '-';
        }
        if (is_string($value)) {
            return $value;
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

        return '-';
    }

}
