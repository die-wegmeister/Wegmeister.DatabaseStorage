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

use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
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
        $titles = [];
        if (isset($entries[0])) {
            foreach ($entries[0]->getProperties() as $title => $value) {
                $titles[] = $title;
            }
            foreach ($entries as $entry) {
                $properties = $entry->getProperties();

                foreach ($properties as &$value) {
                    $value = $this->getStringValue($value);
                }

                $entry->setProperties($properties);
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

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator($this->settings['creator'])
            ->setTitle($this->settings['title'])
            ->setSubject($this->settings['subject']);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setTitle($this->settings['title']);

        $columns = 0;
        foreach ($entries[0]->getProperties() as $title => $value) {
            $columnLetter = $this->getColumnLetter($columns);
            $activeSheet
                ->getCell($columnLetter . '1')
                ->setValueExplicit($title, DataType::TYPE_STRING);
            $columns++;
        }
        if ($exportDateTime) {
            // TODO: Translate title for datetime
            $title = 'DateTime';
            $columnLetter = $this->getColumnLetter($columns);
            $activeSheet
                ->getCell($columnLetter . '1')
                ->setValueExplicit($title, DataType::TYPE_STRING);
            $columns++;
        }

        // Set styles for titles (bold and centered)
        $lastColumnLetter = $this->getColumnLetter($columns - 1);
        $columnStyle = $activeSheet->getStyle('A1:' . $lastColumnLetter . '1');
        $columnStyle->getFont()->setBold(true);
        $columnStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        foreach ($entries as $i => $entry) {
            $columnIndex = 0;

            foreach ($entry->getProperties() as $value) {
                $columnLetter = $this->getColumnLetter($columnIndex);
                $value = $this->getStringValue($value);

                // Use setValueExplicit to prevent Excel from interpreting values as formulas.
                $activeSheet
                    ->getCell($columnLetter . ($i + 2))
                    ->setValueExplicit($value, DataType::TYPE_STRING);
                $columnIndex++;
            }

            if ($exportDateTime) {
                $columnLetter = $this->getColumnLetter($columnIndex);
                $value = $entry->getDateTime()->format($this->settings['datetimeFormat']);
                $activeSheet
                    ->getCell($columnLetter . ($i + 2))
                    ->setValueExplicit($value, DataType::TYPE_STRING);
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
        // For resources return the public uri.
        if ($value instanceof PersistentResource) {
            return $this->resourceManager->getPublicPersistentResourceUri($value) ?: '-';
        }

        // Strings should be return as is.
        if (is_string($value)) {
            return $value;
        }

        // For any object that has a `__toString` method, return the string representation.
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        // For DateTime objects, return the formatted date as defined in Settings.yaml.
        if (isset($value['dateFormat'], $value['date'])) {
            $timezone = null;
            if (isset($value['timezone'])) {
                $timezone = new \DateTimeZone($value['timezone']);
            }
            $dateTime = \DateTime::createFromFormat($value['dateFormat'], $value['date'], $timezone);
            return $dateTime->format($this->settings['datetimeFormat']);
        }

        // For arrays, return the entries as a list with indentation.
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

        // If all else fails, return a dash.
        return '-';
    }

    /**
     * Get column letter for a given index.
     * @param int $index The index to get the prefix for.
     * @return string
     */
    protected function getColumnLetter(int $index): string
    {
        $prefixLetter = '';
        if ($index > 25) {
            $prefixLetter = chr(floor($index / 26) + 64);
        }
        $letter = $prefixLetter . chr(($index % 26) + 65);

        return $letter;
    }
}
