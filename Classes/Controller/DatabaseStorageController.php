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
                    if ($value instanceof PersistentResource) {
                        $value = $this->resourceManager->getPublicPersistentResourceUri($value) ?: '-';
                    } elseif (is_string($value)) {
                    } elseif (is_object($value) && method_exists($value, '__toString')) {
                        $value = (string)$value;
                    } else {
                        $value = '-';
                    }
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

        $dataArray = [];

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator($this->settings['creator'])
            ->setTitle($this->settings['title'])
            ->setSubject($this->settings['subject']);

        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getActiveSheet()->setTitle($this->settings['title']);

        $titles = [];
        $columns = 0;
        foreach ($entries[0]->getProperties() as $title => $value) {
            $titles[] = $title;
            $columns++;
        }
        if ($exportDateTime) {
            // TODO: Translate title for datetime
            $titles[] = 'DateTime';
            $columns++;
        }

        $dataArray[] = $titles;


        foreach ($entries as $entry) {
            $values = [];

            foreach ($entry->getProperties() as $value) {
                if ($value instanceof PersistentResource) {
                    $values[] = $this->resourceManager->getPublicPersistentResourceUri($value) ?: '-';
                } elseif (is_string($value)) {
                    $values[] = $value;
                } elseif (is_object($value) && method_exists($value, '__toString')) {
                    $values[] = (string)$value;
                } else {
                    $values[] = '-';
                }
            }

            if ($exportDateTime) {
                $values[] = $entry->getDateTime()->format($this->settings['datetimeFormat']);
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
}
