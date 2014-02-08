<?php
namespace Librette\Doctrine;

use Doctrine\Common\Annotations\Reader;
use Librette\Doctrine\Annotations\ManipulateMethods;
use Nette\Object;

/**
 * @author David Matejka
 */
class MetadataReader extends Object
{

	protected $metadata = array();

	/** @var \Doctrine\Common\Annotations\Reader */
	protected $annotationReader;


	public function __construct(Reader $annotationReader)
	{
		$this->annotationReader = $annotationReader;
	}


	public function read($entity, $property)
	{
		$className = get_class($entity);
		if (!isset($this->metadata[$className][$property])) {
			$this->metadata[$className][$property] = $this->doRead($entity, $property);
		}

		return $this->metadata[$className][$property];
	}


	protected function doRead($entity, $property)
	{
		$reflectionClass = new \ReflectionClass($entity);
		$config = array(
			'add'    => NULL,
			'remove' => NULL,
			'set'    => NULL,
			'get'    => NULL,
		);
		if(!$reflectionClass->hasProperty($property)) {
			return $config;
		}

		$class = '\Librette\Doctrine\Annotations\ManipulateMethods';
		$reflectionProperty = $reflectionClass->getProperty($property);
		$annotation = $this->annotationReader->getPropertyAnnotation($reflectionProperty, $class);
		if ($annotation && $annotation instanceof ManipulateMethods) {
			$config['add'] = $annotation->add;
			$config['remove'] = $annotation->remove;
			$config['set'] = $annotation->set;
			$config['get'] = $annotation->get;
		}

		return $config;
	}
}