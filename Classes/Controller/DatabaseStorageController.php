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

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;

use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;
use Wegmeister\DatabaseStorage\Service\DatabaseStorageService;

/**
 * The Database Storage controller
 */
#[Flow\Scope("singleton")]
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

    #[Flow\Inject]
    protected DatabaseStorageRepository $databaseStorageRepository;

    #[Flow\Inject]
    protected Translator $translator;

    protected DatabaseStorageService $databaseStorageService;

    /**
     * Show list of identifiers
     */
    public function indexAction(): void
    {
        $this->view->assign('identifiers', $this->databaseStorageRepository->findStorageidentifiers());
    }

    /**
     * List entries of a given storage identifier.
     *
     * @param string $identifier The storage identifier.
     * @param int $currentPage
     */
    public function showAction(string $identifier, int $currentPage = 1): void
    {
        $itemsPerPage = (int)($this->settings['itemsPerPage'] ?? 10);
        $offset = ($currentPage - 1) * $itemsPerPage;

        $this->databaseStorageService = new DatabaseStorageService($identifier);

        $totalEntriesCount = $this->databaseStorageRepository->getAmountOfEntriesByStorageIdentifier($identifier);
        $entries = $this->databaseStorageRepository->findPaginatedByStorageidentifier($identifier, $itemsPerPage, $offset);

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
        $this->view->assign('totalEntriesCount', $totalEntriesCount);
        $this->view->assign('currentPage', $currentPage);
        $this->view->assign('itemsPerPage', $itemsPerPage);

        $numberOfPages = (int)ceil($totalEntriesCount / $itemsPerPage);
        $pages = [];
        for ($page = 1; $page <= $numberOfPages; $page++) {
            $pages[] = [
                'pageNumber' => $page,
                'isCurrent' => ($page === $currentPage),
                'isFirst' => ($page === 1),
                'isLast' => ($page === $numberOfPages),
            ];
        }
        $this->view->assign('pages', $pages);
    }

    /**
     * Delete an entry from the list of identifiers.
     */
    public function deleteAction(DatabaseStorage $entry, bool $removeAttachedResources = false): void
    {
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
     * @param bool $removeAttachedResources Remove attached resources?
     */
    public function deleteAllAction(string $identifier, bool $redirect = false, bool $removeAttachedResources = false): void
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
     */
    public function exportAction(string $identifier, string $writerType = 'Xlsx', bool $exportDateTime = false): void
    {
        if (!isset(self::$types[$writerType])) {
            throw new WriterException('No writer available for type ' . $writerType . '.', 1521787983);
        }

        $this->databaseStorageService = new DatabaseStorageService($identifier);

        $entries = $this->databaseStorageRepository->findByStorageidentifier($identifier);

        $dataArray = [];

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()->setCreator($this->settings['creator'])->setTitle(
            $this->settings['title']
        )->setSubject($this->settings['subject']);

        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getActiveSheet()->setTitle($this->settings['title']);

        $formElementLabels = $this->databaseStorageService->getFormElementLabels(
            $entries
        );
        $columns = count($formElementLabels);

        if ($exportDateTime) {
            // TODO: Translate title for datetime
            $formElementLabels['DateTime'] = 'DateTime';
            $columns++;
        }

        $dataArray[] = $formElementLabels;

        /** @var DatabaseStorage $entry */
        foreach ($entries as $entry) {
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
            $columnStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(
                Alignment::VERTICAL_CENTER
            );

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
}
