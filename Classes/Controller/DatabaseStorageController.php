<?php

/**
 * This file controls the backend of the database storage.
 *
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

namespace Wegmeister\DatabaseStorage\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;

use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;

/**
 * The Database Storage controller
 *
 * @Flow\Scope("singleton")
 */
class DatabaseStorageController extends ActionController
{
    /**
     * Array with extension and mime type for spreadsheet writers.
     *
     * @var array
     */
    protected static $types = [
        'Xls' => [
            'extension' => 'xls',
            'mimeType'  => 'application/vnd.ms-excel',
        ],
        'Xlsx' => [
            'extension' => 'xlsx',
            'mimeType'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'Ods' => [
            'extension' => 'ods',
            'mimeType'  => 'application/vnd.oasis.opendocument.spreadsheet',
        ],
        'Csv' => [
            'extension' => 'csv',
            'mimeType'  => 'text/csv',
        ],
        'Html' => [
            'extension' => 'html',
            'mimeType'  => 'text/html',
        ],
    ];

    /**
     * Instance of the database storage repository.
     *
     * @Flow\Inject
     * @var DatabaseStorageRepository
     */
    protected $databaseStorageRepository;

    /**
     * Instance of the resource manager.
     *
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Instance of the translator interface.
     *
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * Settings of this plugin.
     *
     * @var array
     */
    protected $settings;

    /**
     * Inject the settings
     *
     * @param array $settings The settings to inject.
     *
     * @return void
     */
    public function injectSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * Show list of identifiers
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->assign('identifiers', $this->databaseStorageRepository->findStorageidentifiers());
    }


    /**
     * List entries of a given storage identifier.
     *
     * @param string $identifier The storage identifier.
     *
     * @return void
     * @throws \Exception
     */
    public function showAction(string $identifier)
    {
        $entries = $this->databaseStorageRepository->findByStorageidentifier($identifier);
        $titles = $this->getAllFieldTitles($entries);
        if (isset($entries[0])) {
            foreach ($entries as $entry) {
                $values = [];
                foreach ($entry->getProperties() as $title => $value) {
                    # get field NodeData
                    $fieldNodeData = $this->nodeDataRepository->findByNodeIdentifier($title)[0];
                    if ($fieldNodeData != null) {
                        $type = $fieldNodeData->getNodeType();
                        if (in_array($type->getName(), $this->settings['ignoredExportNodeTypes'])) {
                            continue;
                        }
                        $title = $this->getFieldNodeIdLabelOrIdentifier($fieldNodeData);
                    }

                    if ($value instanceof PersistentResource) {
                        $values[$title] = $this->resourceManager->getPublicPersistentResourceUri($value) ?: '-';
                    } elseif (is_string($value)) {
                        $values[$title] = $value;
                    } elseif (is_array($value)) {
                        $values[$title] = implode(', ', $value);
                    } elseif (is_object($value) && method_exists($value, '__toString')) {
                        $values[$title] = (string)$value;
                    } elseif (isset($value['dateFormat'], $value['date'])) {
                        $timezone = null;
                        if(isset($value['timezone'])){
                            $timezone = new \DateTimeZone($value['timezone']);
                        }
                        $dateTime = \DateTime::createFromFormat($value['dateFormat'], $value['date'], $timezone);
                        $values[$title] = $dateTime->format($this->settings['datetimeFormat']);
                    } else {
                        $values[$title] = '-';
                    }
                }

                # sort values according titles
                $values = array_merge(array_flip($titles), $values);
                $entry->setProperties($values);
            }
            $this->view->assign('identifier', $identifier);
            $this->view->assign('titles', $titles);
            $this->view->assign('entries', $entries);
            $this->view->assign('datetimeFormat', $this->settings['datetimeFormat']);
        } else {
            $this->redirect('index');
        }
    }

    /**
     * Get field titles of all saved entries to allow the export of all fields added/removed over time
     *
     *
     * @param $entries
     * @return array
     */
    protected function getAllFieldTitles($entries)
    {
        $titles = [];

        foreach ($entries as $entry) {
            foreach ($entry->getProperties() as $title => $value) {
                # get field NodeData
                $fieldNodeData = $this->nodeDataRepository->findByNodeIdentifier($title)[0];

                if ($fieldNodeData != null) {
                    $type = $fieldNodeData->getNodeType();
                    if (in_array($type->getName(), $this->settings['ignoredExportNodeTypes'])) {
                        continue;
                    }
                    # override identifier
                    $title = $this->getFieldNodeIdLabelOrIdentifier($fieldNodeData);
                }

                $titles[$title] = $title;
            }
        }
        $titles = array_unique($titles);
        return $titles;
    }

    /**
     * Get field label if no ID was set. use identifier as fallback only
     *
     * @param $fieldNodeData
     * @return string
     */
    protected function getFieldNodeIdLabelOrIdentifier($fieldNodeData)
    {
        $identifier = $fieldNodeData->getIdentifier();
        return $fieldNodeData->getProperty('identifier') == null && $fieldNodeData->getProperty(
            'label'
        ) == null ? $identifier : $fieldNodeData->getProperty('label') . '_label';
    }

    /**
     * Delete an entry from the list of identifiers.
     *
     * @param DatabaseStorage $entry The DatabaseStorage entry
     *
     * @return void
     */
    public function deleteAction(DatabaseStorage $entry)
    {
        $identifier = $entry->getStorageidentifier();
        $this->databaseStorageRepository->remove($entry);
        $this->addFlashMessage($this->translator->translateById('storage.flashmessage.entryRemoved', [], null, null, 'Main', 'Wegmeister.DatabaseStorage'));
        $this->redirect('show', null, null, ['identifier' => $identifier]);
    }


    /**
     * Delete all entries for the given identifier.
     *
     * @param string $identifier The storage identifier for the entries to be removed.
     * @param bool   $redirect   Redirect to index?
     *
     * @return void
     */
    public function deleteAllAction(string $identifier, bool $redirect = false)
    {
        $count = 0;
        foreach ($this->databaseStorageRepository->findByStorageidentifier($identifier) as $entry) {
            $this->databaseStorageRepository->remove($entry);
            $count++;
        }

        $this->view->assign('identifier', $identifier);
        $this->view->assign('count', $count);

        if ($redirect) {
            // TODO: Translate flash message.
            $this->addFlashMessage($this->translator->translateById('storage.flashmessage.entriesRemoved', [], null, null, 'Main', 'Wegmeister.DatabaseStorage'));
            $this->redirect('index');
        }
    }


    /**
     * Export all entries for a specific identifier as xls.
     *
     * @param string $identifier     The storage identifier that should be exported.
     * @param string $writerType     The writer type/export format to be used.
     * @param bool   $exportDateTime Should the datetime be exported?
     *
     * @return void
     */
    public function exportAction(string $identifier, string $writerType = 'Xlsx', bool $exportDateTime = false)
    {
        if (!isset(self::$types[$writerType])) {
            throw new WriterException('No writer available for type ' . $writerType . '.', 1521787983);
        }

        $entries = $this->databaseStorageRepository->findByStorageidentifier($identifier)->toArray();

        $dataArray = [];

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator($this->settings['creator'])
            ->setTitle($this->settings['title'])
            ->setSubject($this->settings['subject']);

        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getActiveSheet()->setTitle($this->settings['title']);

        $titles = $this->getAllFieldTitles($entries);
        $columns = count($titles);

        if ($exportDateTime) {
            // TODO: Translate title for datetime
            $titles[] = 'DateTime';
            $columns++;
        }

        $dataArray[] = $titles;

        foreach ($entries as $entry) {
            $values = [];

            foreach ($entry->getProperties() as $title => $value) {
                # get field NodeData
                $fieldNodeData = $this->nodeDataRepository->findByNodeIdentifier($title)[0];
                if ($fieldNodeData != null) {
                    $type = $fieldNodeData->getNodeType();
                    if (in_array($type->getName(), $this->settings['ignoredExportNodeTypes'])) {
                        continue;
                    }
                    $title = $this->getFieldNodeIdLabelOrIdentifier($fieldNodeData);
                }
                if ($value instanceof PersistentResource) {
                    $values[$title] = $this->resourceManager->getPublicPersistentResourceUri($value) ?: '-';
                } elseif (is_string($value)) {
                    $values[$title] = $value;
                } elseif (is_array($value)) {
                    $values[$title] = implode(', ', $value);
                } elseif (is_object($value) && method_exists($value, '__toString')) {
                    $values[$title] = (string)$value;
                } elseif (isset($value['dateFormat'], $value['date'])) {
                    $timezone = null;
                    if(isset($value['timezone'])){
                        $timezone = new \DateTimeZone($value['timezone']);
                    }
                    $dateTime = \DateTime::createFromFormat($value['dateFormat'], $value['date'], $timezone);
                    $values[$title] = $dateTime->format($this->settings['datetimeFormat']);
                } else {
                    $values[$title] = '-';
                }
            }

            # sort values according titles
            $values = array_merge(array_flip($titles), $values);

            if ($exportDateTime) {
                $values['DateTime'] = $entry->getDateTime()->format($this->settings['datetimeFormat']);
            }

            $dataArray[] = $values;
        }

        $spreadsheet->getActiveSheet()->fromArray($dataArray);

        // Set headlines bold
        $prefixIndex = 64;
        $prefixKey = '';
        for ($i = 0; $i < $columns; $i++) {
            $index = $i % 26;
            $columnStyle = $spreadsheet->getActiveSheet()->getStyle($prefixKey . chr(65 + $index) . '1');
            $columnStyle->getFont()->setBold(true);
            $columnStyle->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            if ($index + 1 > 25) {
                $prefixIndex++;
                $prefixKey = chr($prefixIndex);
            }
        }


        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header("Pragma: public"); // required
        header("Expires: 0");
        header('Cache-Control: max-age=0');
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false); // required for certain browsers
        header('Content-Type: ' . self::$types[$writerType]['mimeType']);
        header(
            sprintf(
                'Content-Disposition: attachment; filename="Database-Storage-%s.%s"',
                $identifier,
                self::$types[$writerType]['extension']
            )
        );
        header("Content-Transfer-Encoding: binary");

        $writer = IOFactory::createWriter($spreadsheet, $writerType);
        $writer->save('php://output');
        exit;
    }

    /**
     * Internal function to replace value with a string for export / listing.
     *
     * @param mixed $value  The database column value.
     * @param int   $indent The level of indentation (for array values).
     *
     * @return string
     */
    protected function getStringValue($value, int $indent = 0): string
    {
        if ($value instanceof PersistentResource) {
            return $this->resourceManager->getPublicPersistentResourceUri($value) ?: '-';
        } elseif (is_string($value)) {
            return $value;
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        } elseif (isset($value['dateFormat'], $value['date'])) {
            $timezone = null;
            if (isset($value['timezone'])) {
                $timezone = new \DateTimeZone($value['timezone']);
            }
            $dateTime = \DateTime::createFromFormat($value['dateFormat'], $value['date'], $timezone);
            return $dateTime->format($this->settings['datetimeFormat']);
        } elseif (is_array($value)) {
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
