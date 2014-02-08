<?php
namespace Librette\Doctrine;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Proxy\Proxy;
use Nette\Object;

/**
 * @author David Matejka
 */
class WrappedEntity extends Object
{

	/** @var array|null */
	private $identifier;

	/** @var bool */
	protected $identifierInitialized = FALSE;

	/** @var boolean True if entity or proxy is loaded */
	private $entityInitialized = FALSE;

	/** @var \Doctrine\ORM\Mapping\ClassMetadata */
	protected $metadata;

	/** @var object wrapped entity */
	protected $entity;

	/** @var \Doctrine\ORM\EntityManager */
	protected $em;

	/** @var \Librette\Doctrine\MetadataReader */
	protected $metadataReader;

	/** @var \Librette\Doctrine\EntityWrapper */
	protected $entityWrapper;


	/**
	 * Wrap entity
	 *
	 * @param object $entity
	 * @param \Doctrine\ORM\EntityManager $em
	 */
	public function __construct($entity, EntityManager $em, MetadataReader $metadataReader, EntityWrapper $entityWrapper)
	{
		$this->em = $em;
		$this->entity = $entity;
		$this->metadataReader = $metadataReader;
		$this->metadata = $em->getClassMetadata(get_class($this->entity));
		$this->entityWrapper = $entityWrapper;
	}


	/**
	 * @return object
	 */
	public function getEntity()
	{
		return $this->entity;
	}


	/**
	 * @return ClassMetadata
	 */
	public function getMetadata()
	{
		return $this->metadata;
	}


	/**
	 * @return bool
	 */
	public function hasValidIdentifier()
	{
		return $this->getIdentifier() !== NULL;
	}


	/**
	 * @return array|null
	 */
	public function getIdentifier()
	{
		$this->initializeIdentifier();

		return $this->identifier;
	}


	/**
	 * @return mixed|null
	 */
	public function getSingleIdentifier()
	{
		$identifier = $this->getIdentifier();
		if (is_array($identifier)) {
			$identifier = reset($identifier);
		}

		return $identifier;

	}


	/**
	 * @param string $association association name
	 * @return bool
	 */
	public function isToOneAssociation($association)
	{
		$associationMapping = $this->getAssociationMapping($association);

		return (bool) ($associationMapping['type'] & ClassMetadata::TO_ONE);
	}


	/**
	 * @param string $association association name
	 * @return int
	 */
	public function isToManyAssociation($association)
	{
		$associationMapping = $this->getAssociationMapping($association);

		return (bool) ($associationMapping['type'] & ClassMetadata::TO_MANY);
	}


	/**
	 * @param array $data
	 * @return $this
	 */
	public function populate(array $data)
	{
		foreach ($data as $field => $value) {
			$this->setRawValue($field, $value);
		}

		return $this;
	}


	/**
	 * @param string $property
	 * @return mixed|null
	 */
	public function getValue($property)
	{
		$this->initializeEntity();

		if ($method = $this->getCustomManipulateMethod($property, 'get')) {
			return call_user_func(array($this->entity, $method));
		}

		$methods = array();
		$methods[] = 'get' . $property;
		if ($this->tryCallMethods($methods, array(), $result)) {
			return $result;
		}

		return $this->getRawValue($property);
	}


	/**
	 * @param string $field
	 * @return mixed
	 * @throws InvalidFieldException
	 */
	public function getRawValue($field)
	{
		$this->initializeEntity();
		if (!isset($this->metadata->reflFields[$field])) {
			throw InvalidFieldException::fieldNotExist($this->entity, $field);
		}

		return $this->metadata->getFieldValue($this->entity, $field);
	}


	/**
	 * @param string $field
	 * @return mixed|null
	 */
	public function tryGetValue($field)
	{
		try {
			return $this->getValue($field);
		} catch (InvalidFieldException $e) {
			return NULL;
		}
	}


	/**
	 * @param string $field
	 * @param mixed $value
	 */
	public function setValue($field, $value)
	{
		$this->initializeEntity();
		$metadata = $this->metadata;
		if ($metadata->hasAssociation($field)) {
			$association = $this->getAssociationMapping($field);
			$repository = $this->em->getRepository($association['targetEntity']);
			if (!$value) {
				$value = NULL;
			} elseif (!is_object($value)) {
				$value = $repository->find($value);
			}
		}
		if ($method = $this->getCustomManipulateMethod($field, 'set')) {
			call_user_func_array(array($this->entity, $method), array($value));

			return;
		}

		$methods = array();
		$methods[] = 'set' . $field;
		if ($this->tryCallMethods($methods, array($value))) {
			return;
		}
		$this->setRawValue($field, $value);
	}


	/**
	 * @param string $field
	 * @param mixed $value
	 * @throws InvalidFieldException
	 */
	public function setRawValue($field, $value)
	{
		$this->initializeEntity();
		if (!isset($this->metadata->reflFields[$field])) {
			throw InvalidFieldException::fieldNotExist($this->entity, $field);
		}
		$this->metadata->setFieldValue($this->entity, $field, $value);
	}


	/**
	 * @param string $field
	 * @param mixed $value
	 * @return bool
	 */
	public function trySetValue($field, $value)
	{
		try {
			$this->setValue($field, $value);

			return TRUE;
		} catch (InvalidFieldException $e) {
			return FALSE;
		}
	}


	/**
	 * @param string $association
	 * @param object $associatedEntity
	 */
	public function addToCollection($association, $associatedEntity)
	{
		$this->initializeEntity();
		$this->verifyToManyAssociation($association);
		$this->verifyAssociatedEntity($association, $associatedEntity);

		if ($method = $this->getCustomManipulateMethod($association, 'add')) {
			call_user_func_array(array($this->entity, $method), array($associatedEntity));

			return;
		}

		$methods = array();
		$methods[] = 'add' . $association;
		if ($this->tryCallMethods($methods, array($associatedEntity))) {
			return;
		}

		$collection = $this->getCollectionFromAssociation($association);
		if (!$collection->contains($associatedEntity)) {
			$collection->add($associatedEntity);
		}
		$this->setRawValue($association, $collection);
		$associationMapping = $this->getAssociationMapping($association);
		if (($inversed = $associationMapping['mappedBy']) || ($inversed = $associationMapping['inversedBy'])) {
			$wrapper = $this->entityWrapper->wrap($associatedEntity);
			$wrapper->setRawValue($inversed, $this->entity);
		}
	}


	/**
	 * @param string $association
	 * @param object $associatedEntity
	 */
	public function removeFromCollection($association, $associatedEntity)
	{
		$this->initializeEntity();
		$this->verifyToManyAssociation($association);
		$this->verifyAssociatedEntity($association, $associatedEntity);

		if ($method = $this->getCustomManipulateMethod($association, 'remove')) {
			call_user_func_array(array($this->entity, $method), array($associatedEntity));

			return;
		}

		$methods = array();
		$methods[] = 'remove' . $association;
		if ($this->tryCallMethods($methods, array($associatedEntity))) {
			return;
		}

		$collection = $this->getCollectionFromAssociation($association);
		if ($collection->contains($associatedEntity)) {
			$collection->removeElement($associatedEntity);
		}
		$this->setRawValue($association, $collection);
		$associationMapping = $this->getAssociationMapping($association);
		if (($inversed = $associationMapping['mappedBy']) || ($inversed = $associationMapping['inversedBy'])) {
			$wrapper = $this->entityWrapper->wrap($associatedEntity);
			$wrapper->setRawValue($inversed, NULL);
		}
	}


	/**
	 * @param string $association
	 * @return Collection
	 * @throws UnexpectedValueException
	 */
	protected function getCollectionFromAssociation($association)
	{
		$collection = $this->metadata->getFieldValue($this->entity, $association);
		if (!$collection instanceof Collection) {
			throw UnexpectedValueException::notACollection($this->entity, $association);
		}

		return $collection;
	}


	private function initializeIdentifier()
	{
		if ($this->identifier !== NULL) {
			return;
		}
		if ($this->entity instanceof Proxy) {
			$this->initializeEntity();
			$uow = $this->em->getUnitOfWork();
			if ($uow->isInIdentityMap($this->entity)) {
				$this->identifier = $uow->getEntityIdentifier($this->entity);
			}
		}
		if ($this->identifier !== NULL) {
			return;
		}

		$this->identifier = array();
		foreach ($this->metadata->identifier as $name) {
			$this->identifier[$name] = $id = $this->getRawValue($name);
			if ($id === NULL) {
				$this->identifier = NULL;
				break;
			}
		}

	}


	protected function initializeEntity()
	{
		if ($this->entityInitialized) {
			return;
		}
		if ($this->entity instanceof Proxy && !$this->entity->__isInitialized__) {
			$this->entity->__load();
		}
		$this->entityInitialized = TRUE;
	}


	/**
	 * @param array $methods method names
	 * @param array $args argument for method
	 * @param mixed $result if provided, then it is filled with method return value
	 * @return bool
	 */
	protected function tryCallMethods(array $methods = array(), array $args = array(), &$result = NULL)
	{
		foreach ($methods as $method) {
			if (method_exists($this->entity, $method)) {
				$result = call_user_func_array(array($this->entity, $method), $args);

				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * @param string $association
	 * @return array
	 * @throws InvalidAssociationException
	 */
	protected function getAssociationMapping($association)
	{
		try {
			return $this->metadata->getAssociationMapping($association);
		} catch (MappingException $e) {
			throw InvalidAssociationException::associationNotExist($this->entity, $association, $e);
		}
	}


	/**
	 * @param string $property
	 * @param string $method add, get, remove or set
	 * @return string|null method name
	 */
	protected function getCustomManipulateMethod($property, $method)
	{
		$config = $this->metadataReader->read($this->entity, $property);

		return isset($config[$method]) ? $config[$method] : NULL;
	}


	/**
	 * @param string $association
	 * @throws InvalidAssociationException
	 */
	protected function verifyToManyAssociation($association)
	{
		if (!$this->isToManyAssociation($association)) {
			throw InvalidAssociationException::notToManyAssociation($this->entity, $association);
		}
	}


	/**
	 * @param string $association
	 * @param object $associatedEntity
	 * @throws InvalidAssociationException
	 */
	protected function verifyAssociatedEntity($association, $associatedEntity)
	{
		$associationMapping = $this->getAssociationMapping($association);
		if (!$associatedEntity instanceof $associationMapping['targetEntity']) {
			throw InvalidAssociationException::invalidTargetEntity($this->entity, $association, $associationMapping['targetEntity'], $associatedEntity);
		}
	}

}
