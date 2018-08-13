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

/**
 * @Flow\Entity
 */
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
     * DateTime the entry was created.
     *
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
     *
     * @param string $identifier The identifier for the entry.
     *
     * @return DatabaseStorage
     */
    public function setStorageidentifier(string $identifier)
    {
        $this->storageidentifier = $identifier;
        return $this;
    }

    /**
     * Get properties
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Set properties
     *
     * @param array $properties Array of the properties.
     *
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
     *
     * @param \DateTime $datetime
     *
     * @return DatabaseStorage
     */
    public function setDateTime(\DateTime $datetime)
    {
        $this->datetime = $datetime;
        return $this;
    }
}
