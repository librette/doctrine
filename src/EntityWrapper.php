<?php
namespace Librette\Doctrine;

use Doctrine\ORM\EntityManager;
use Nette\Object;

/**
 * @author David Matejka
 */
class EntityWrapper extends Object
{

	/** @var \Doctrine\ORM\EntityManager */
	protected $em;

	/** @var \Librette\Doctrine\MetadataReader */
	protected $metadataReader;

	/** @var array */
	protected $wrappedEntities = array();


	/**
	 * @param MetadataReader $metadataReader
	 * @param EntityManager $em
	 */
	public function __construct(MetadataReader $metadataReader, EntityManager $em)
	{
		$this->em = $em;
		$this->metadataReader = $metadataReader;
	}


	/**
	 * @param object $entity
	 * @return WrappedEntity
	 */
	public function wrap($entity)
	{
		$hash = spl_object_hash($entity);
		if (!isset($this->wrappedEntities[$hash])) {
			$this->wrappedEntities[$hash] = new WrappedEntity($entity, $this->em, $this->metadataReader, $this);
		}

		return $this->wrappedEntities[$hash];
	}
}