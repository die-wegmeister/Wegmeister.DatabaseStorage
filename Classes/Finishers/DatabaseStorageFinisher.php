<?php
namespace Wegmeister\DatabaseStorage\Finishers;

/**
 * This script belongs to the Neos Flow package "Wegmeister.DatabaseStorage".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Lesser General Public License, either version 3
 * of the License, or (at your option) any later version.
 *
 * The Neos project - inspiring people to share!
 */

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
     * @Flow\Inject
     * @var DatabaseStorageRepository
     */
    protected $databaseStorageRepository;

    /**
     * Executes this finisher
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
