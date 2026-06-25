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
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Form\Core\Model\AbstractFinisher;
use Neos\Media\Domain\Model\ResourceBasedInterface;

use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;
use Wegmeister\DatabaseStorage\Service\DatabaseStorageService;

/**
 * A simple finisher that stores data into database
 */
class DatabaseStorageFinisher extends AbstractFinisher
{
    #[Flow\Inject]
    protected DatabaseStorageRepository $databaseStorageRepository;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    #[Flow\Inject]
    protected DatabaseStorageService $databaseStorageService;

    /**
     * Executes this finisher
     *
     * @see AbstractFinisher::execute()
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    protected function executeInternal(): void
    {
        $formRuntime = $this->finisherContext->getFormRuntime();
        $formValues = $formRuntime->getFormState()->getFormValues();

        $storageIdentifier = $this->parseOption('storageIdentifier');
        if ($storageIdentifier) {
            $this->databaseStorageService = new DatabaseStorageService($storageIdentifier);
        } else {
            $storageIdentifier = '__undefined__';
        }

        foreach ($formValues as $formElementIdentifier => $formValue) {
            if ($formValue instanceof ResourceBasedInterface) {
                $formValues[$formElementIdentifier] = $formValue->getResource();
            }
            if ($storageIdentifier && $this->databaseStorageService->formElementIdentifierMustBeIgnoredInFinisher($formElementIdentifier)) {
                unset($formValues[$formElementIdentifier]);
            }
        }

        $dbStorage = new DatabaseStorage();
        $dbStorage
            ->setStorageidentifier($storageIdentifier)
            ->setProperties($formValues)
            ->setDateTime(new \DateTime());

        $this->databaseStorageRepository->add($dbStorage);

        // Persist the object to the database, so we can get the identifier...
        $this->persistenceManager->persistAll();

        // ... then get the identifier
        $dbIdentifier = $this->persistenceManager->getIdentifierByObject($dbStorage);

        // ... and add it to the form state
        $formRuntime->getFormState()->setFormValue('databaseStorageIdentifier', $dbIdentifier);
    }
}
