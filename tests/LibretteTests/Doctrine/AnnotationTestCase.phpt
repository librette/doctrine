<?php
namespace Librette\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use LibretteTests\Doctrine\CmsUser;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/models/model.php';

class AnnotationTestCase extends TestCase
{
	/** @var MetadataReader */
	protected $metadataReader;

	public function setup()
	{
		AnnotationRegistry::registerLoader("class_exists");
		$annotationReader = new AnnotationReader();
		$this->metadataReader = new MetadataReader($annotationReader);
	}

	public function testRead()
	{
		$entity = new CmsUser();

		$methods = $this->metadataReader->read($entity, 'phoneNumbers');
		Assert::type('array', $methods);
		Assert::same('addPhoneNumber', $methods['add']);
		Assert::same('removePhoneNumber', $methods['remove']);
	}
}

run(new AnnotationTestCase());