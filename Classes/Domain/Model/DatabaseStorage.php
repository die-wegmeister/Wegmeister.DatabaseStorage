<?php
namespace Wegmeister\DatabaseStorage\Domain\Model;

/**
 * This file is part of the Wegmeister.DatabaseStorage package.
 */

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

/**
 * @Flow\Entity
 */
class DatabaseStorage
{

    /**
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
     * @var \DateTime
     * @Flow\Validate(type="NotEmpty")
     */
    protected $datetime;

    /**
     * Get identifier
     *
     * @return string
     */
    public function getStorageidentifier()
    {
        return $this->storageidentifier;
    }

    /**
     * Set the identifier
     * @param string $identifier
     * @return DatabaseStorage
     */
    public function setStorageidentifier(string $identifier)
    {
        $this->storageidentifier = $identifier;
        return $this;
    }

    /**
     * Get properties
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Set properties
     * @param array $properties
     * @return DatabaseStorage
     */
    public function setProperties(array $properties)
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * Get datetime
     *
     * @return \DateTime
     */
    public function getDateTime()
    {
        return $this->datetime;
    }

    /**
     * Set datetime
     * @param \DateTime $datetime
     * @return DatabaseStorage
     */
    public function setDateTime(\DateTime $datetime)
    {
        $this->datetime = $datetime;
        return $this;
    }
}
