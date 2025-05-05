<?php

/**
 * This file controls the backend of the database storage.
 *
 * This file is part of the Flow Framework Package "Wegmeister.DatabaseStorage".
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

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;

use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;
use Wegmeister\DatabaseStorage\Service\DatabaseStorageService;

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
            'mimeType' => 'application/vnd.ms-excel',
        ],
        'Xlsx' => [
            'extension' => 'xlsx',
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'Ods' => [
            'extension' => 'ods',
            'mimeType' => 'application/vnd.oasis.opendocument.spreadsheet',
        ],
        'Csv' => [
            'extension' => 'csv',
            'mimeType' => 'text/csv',
        ],
        'Html' => [
            'extension' => 'html',
            'mimeType' => 'text/html',
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
     * @var DatabaseStorageService
     */
    protected $databaseStorageService;

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
        $this->databaseStorageService = new DatabaseStorageService($identifier);

        $entries = $this->databaseStorageRepository->findByStorageidentifier($identifier);
        $formElementLabels = $this->databaseStorageService->getFormElementLabels(
            $entries
        );

        if (empty($entries)) {
            $this->redirect('index');
        }

        /** @var DatabaseStorage $entry */
        foreach ($entries as $entry) {
            $values = [];
            foreach ($formElementLabels as $formElementLabel) {
                $values[$formElementLabel] = $this->databaseStorageService->getValueFromEntryProperty(
                    $entry,
                    $formElementLabel
                );
            }
            $entry->setProperties($values);
        }

        $this->view->assign('identifier', $identifier);
        $this->view->assign('titles', $formElementLabels);
        $this->view->assign('entries', $entries);
        $this->view->assign('datetimeFormat', $this->settings['datetimeFormat']);
    }

    /**
     * Delete an entry from the list of identifiers.
     *
     * @param DatabaseStorage $entry The DatabaseStorage entry
     *
     * @return void
     */
    public function deleteAction(
        DatabaseStorage $entry,
        bool $removeAttachedResources = false
    ) {
        $identifier = $entry->getStorageidentifier();
        $this->databaseStorageRepository->remove($entry, $removeAttachedResources);
        $this->addFlashMessage(
            $this->translator->translateById(
                'storage.flashmessage.entryRemoved',
                [],
                null,
                null,
                'Main',
                'Wegmeister.DatabaseStorage'
            )
        );
        $this->redirect('show', null, null, ['identifier' => $identifier]);
    }

    /**
     * Delete all entries for the given identifier.
     *
     * @param string $identifier The storage identifier for the entries to be removed.
     * @param bool $redirect Redirect to index?
     * @param bool $removeAttachedResource Remove attached resources?
     *
     * @return void
     */
    public function deleteAllAction(string $identifier, bool $redirect = false, bool $removeAttachedResources = false)
    {
        $count = $this->databaseStorageRepository->deleteByStorageIdentifierAndDateInterval(
            $identifier,
            null,
            $removeAttachedResources
        );

        $this->view->assign('identifier', $identifier);
        $this->view->assign('count', $count);

        if ($redirect) {
            // TODO: Translate flash message.
            $this->addFlashMessage(
                $this->translator->translateById(
                    'storage.flashmessage.entriesRemoved',
                    [],
                    null,
                    null,
                    'Main',
                    'Wegmeister.DatabaseStorage'
                )
            );
            $this->redirect('index');
        }
    }

    /**
     * Export all entries for a specific identifier as xls.
     *
     * @param string $identifier The storage identifier that should be exported.
     * @param string $writerType The writer type/export format to be used.
     * @param bool $exportDateTime Should the datetime be exported?
     *
     * @return void
     */
    public function exportAction(string $identifier, string $writerType = 'Xlsx', bool $exportDateTime = false)
    {
        if (!isset(self::$types[$writerType])) {
            throw new WriterException('No writer available for type ' . $writerType . '.', 1521787983);
        }

        $this->databaseStorageService = new DatabaseStorageService($identifier);

        $entries = $this->databaseStorageRepository->findByStorageidentifier($identifier);

        $dataArray = [];

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator($this->settings['creator'])
            ->setTitle($this->settings['title'])
            ->setSubject($this->settings['subject']);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setTitle($this->settings['title']);

        $formElementLabels = $this->databaseStorageService->getFormElementLabels(
            $entries
        );
        $columns = count($formElementLabels);

        if ($exportDateTime) {
            // TODO: Translate title for datetime
            $formElementLabels['DateTime'] = 'DateTime';
            $columns++;
        }

        $row = 1;
        $this->setRowValues($activeSheet, $row, $formElementLabels);

        // Set styles for titles (bold and centered)
        $lastColumnLetter = $this->getColumnLetter($columns - 1);
        $columnStyle = $activeSheet->getStyle('A1:' . $lastColumnLetter . '1');
        $columnStyle->getFont()->setBold(true);
        $columnStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        /** @var DatabaseStorage $entry */
        foreach ($entries as $entry) {
            $row++;
            $values = [];
            foreach ($formElementLabels as $formElementLabel) {
                $values[$formElementLabel] = $this->databaseStorageService->getValueFromEntryProperty(
                    $entry,
                    $formElementLabel
                );
            }
            if ($exportDateTime) {
                $values['DateTime'] = $entry->getDateTime()->format($this->settings['datetimeFormat']);
            }
            $this->setRowValues($activeSheet, $row, $values);
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
     * Set values of a row. Use explicit string type to prevent calculation of formulas.
     *
     * @param Worksheet $sheet The sheet to set the values on.
     * @param int       $row The row to set the values on.
     * @param string[]  $values The values to set.
     * @return void
     */
    protected function setRowValues(Worksheet $sheet, int $row, array $values)
    {
        $index = 0;
        foreach ($values as $value) {
            $letter = $this->getColumnLetter($index);
            $sheet->setCellValueExplicit(
                $letter . $row,
                $value,
                DataType::TYPE_STRING
            );
            $index++;
        }
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
