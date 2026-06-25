<?php

declare(strict_types=1);

namespace Wegmeister\DatabaseStorage\FusionForm\Runtime\Action;

/**
 * The Fusion form action for the database storage.
 *
 * This file is part of the Flow Framework Package "Wegmeister.DatabaseStorage".
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Fusion\Form\Runtime\Action\AbstractAction;
use Neos\Fusion\Form\Runtime\Domain\Exception\ActionException;
use Neos\Media\Domain\Model\ResourceBasedInterface;

use Wegmeister\DatabaseStorage\Domain\Model\DatabaseStorage;
use Wegmeister\DatabaseStorage\Domain\Repository\DatabaseStorageRepository;

class DatabaseStorageAction extends AbstractAction
{
    #[Flow\Inject]
    protected DatabaseStorageRepository $databaseStorageRepository;

    /**
     * @return ActionResponse|null
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function perform(): ?ActionResponse
    {
        $storageIdentifier = $this->options['storageIdentifier'];
        $formValues = $this->options['formValues'];

        if (!$storageIdentifier) {
            $storageIdentifier = '__undefined__';
        }

        foreach ($formValues as $formElementIdentifier => $formValue) {
            if ($formValue instanceof ResourceBasedInterface) {
                $formValues[$formElementIdentifier] = $formValue->getResource();
            }
        }

        $dbStorage = new DatabaseStorage();
        $dbStorage
            ->setStorageidentifier($storageIdentifier)
            ->setProperties($formValues)
            ->setDateTime(new \DateTime());

        $this->databaseStorageRepository->add($dbStorage);

        return null;
    }
}
