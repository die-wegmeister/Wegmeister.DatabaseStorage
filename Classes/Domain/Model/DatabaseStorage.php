<?php
/**
 * The model for a database storage entry.
 *
 * This file is part of the Flow Framework Package "Wegmeister.DatabaseStorage".
 *
 * PHP version 7
 *
 * @category Model
 * @package  Wegmeister\DatabaseStorage
 * @author   Benjamin Klix <benjamin.klix@die-wegmeister.com>
 * @license  https://github.com/die-wegmeister/Wegmeister.DatabaseStorage/blob/master/LICENSE GPL-3.0-or-later
 * @link     https://github.com/die-wegmeister/Wegmeister.DatabaseStorage
 */
namespace Wegmeister\DatabaseStorage\Domain\Model;

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

#[Flow\Entity]
class DatabaseStorage
{

    /**
     * The storage identifier of the entry.
     *
     * @var string
     * @Flow\Validate(type="NotEmpty")
     * @ORM\Column(length=256)
     * @Flow\Validate(type="StringLength", options={ "minimum"=1, "maximum"=256 })
     */
    protected $storageidentifier;

    /**
     * Properties of the current storage
     *
     * @ORM\Column(type="flow_json_array")
     * @var array<mixed>
     */
    protected $properties = [];

    /**
     * @Flow\Validate(type="NotEmpty")
     */
    protected \DateTime $datetime;

    public function getStorageidentifier(): string
    {
        return $this->storageidentifier;
    }

    public function setStorageidentifier(string $storageIdentifier): DatabaseStorage
    {
        $this->storageidentifier = $storageIdentifier;
        return $this;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setProperties(array $properties): DatabaseStorage
    {
        $this->properties = $properties;
        return $this;
    }

    public function getDateTime(): \DateTime
    {
        return $this->datetime;
    }

    public function setDateTime(\DateTime $datetime): DatabaseStorage
    {
        $this->datetime = $datetime;
        return $this;
    }
}
