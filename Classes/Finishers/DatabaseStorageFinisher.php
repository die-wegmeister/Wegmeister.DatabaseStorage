<?php
/**
 * The form finisher for the database storage.
 *
 * This file is part of the Flow Framework Package "Wegmeister.DatabaseStorage".
 *
 * PHP version 7
 *
 * @category Finisher
 * @package  Wegmeister\DatabaseStorage
 * @author   Benjamin Klix <benjamin.klix@die-wegmeister.com>
 * @license  https://github.com/die-wegmeister/Wegmeister.DatabaseStorage/blob/master/LICENSE GPL-3.0-or-later
 * @link     https://github.com/die-wegmeister/Wegmeister.DatabaseStorage
 */
namespace Wegmeister\DatabaseStorage\Finishers;

use Neos\Flow\Annotations as Flow;
use Neos\Form\Core\Model\AbstractFinisher;
use Neos\Form\Exception\FinisherException;

use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;

/**
 * A simple finisher that stores data into database
 */
class DatabaseStorageFinisher extends AbstractFinisher
{
    /**
     * Instance of the database storage repository.
     *
     * @Flow\Inject
     * @var DatabaseStorageRepository
     */
    protected $databaseStorageRepository;

    /**
     * Executes this finisher
     *
     * @see AbstractFinisher::execute()
     *
     * @return void
     * @throws FinisherException
     */
    protected function executeInternal()
    {
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formValues = $formRuntime->getFormState()->getFormValues();

        $identifier = $this->parseOption('identifier');
        if (!$identifier) {
            $identifier = '__undefined__';
        }

        $dbStorage = new DatabaseStorage();
        $dbStorage
            ->setStorageidentifier($identifier)
            ->setProperties($formValues)
            ->setDateTime(new \DateTime());

        $this->databaseStorageRepository->add($dbStorage);
    }
}
