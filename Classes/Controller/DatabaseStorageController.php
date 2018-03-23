<?php
namespace Wegmeister\DatabaseStorage\Controller;

/**
 * This file is part of the RadKultur.Wettbewerb package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;

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
            'mimeType'  => '',
        ],
        'Html' => [
            'extension' => 'html',
            'mimeType'  => 'text/html',
        ],
    ];

    /**
     * @Flow\Inject
     * @var DatabaseStorageRepository
     */
    protected $databaseStorageRepository;


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
     * Export all entries for a specific identifier as xls.
     *
     * @param string $identifier
     * @param string $writerType
     *
     * @return void
     */
    public function exportAction(string $identifier, $writerType = 'Xlsx')
    {
        if (!isset(self::$types[$writerType])) {
            throw new WriterException('No writer available for type ' . $writerType . '.', 1521787983);
        }

        $entries = $this->databaseStorageRepository->findByStorageidentifier($identifier)->toArray();

        $dataArray = [];

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator('die wegmeister gmbh')
            ->setTitle('Database Export')
            ->setSubject('Database Export');

        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getActiveSheet()->setTitle('Database-Export');

        $titles = [];
        $columns = 0;
        foreach ($entries[0]->getProperties() as $title => $value) {
            $titles[] = $title;
            $columns++;
        }

        $dataArray[] = $titles;


        foreach ($entries as $entry) {
            $values = [];

            foreach ($entry->getProperties() as $value) {
                $values[] = $value;
            }

            $dataArray[] = $values;
        }

        $spreadsheet->getActiveSheet()->fromArray($dataArray);

        // TODO: Set headline bold
        $prefixIndex = 64;
        $prefixKey = '';
        for ($i = 0; $i < $columns; $i++) {
            $index = $i % 26;
            $columnStyle = $spreadsheet->getActiveSheet()->getStyle($prefixKey . chr(65 + $i) . '1');
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
        header(sprintf(
            'Content-Disposition: attachment; filename="Database-Storage-%s.%s"',
            $identifier,
            self::$types[$writerType]['extension']
        ));
        header("Content-Transfer-Encoding: binary");

        $writer = IOFactory::createWriter($spreadsheet, $writerType);
        $writer->save('php://output');
        exit;
    }
}
