<?php
namespace LibretteTests\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kdyby\Doctrine\EntityManager;
use Librette\Doctrine\EntityWrapper;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/models/model.php';

class WrappedEntityTestCase extends ORMTestCase
{

	/** @var EntityManager */
	protected $em;

	/** @var EntityWrapper */
	protected $entityWrapper;


	protected function setUp()
	{
		$this->em = $this->createMemoryManager();
		$this->entityWrapper = $this->serviceLocator->getByType('Librette\Doctrine\EntityWrapper');
	}

	public function testBasicFunctions()
	{
		$user = new CmsUser();
		$wrapped = $this->entityWrapper->wrap($user);
		Assert::same($user, $wrapped->getEntity());
		Assert::true($wrapped->getMetadata() instanceof ClassMetadata);

		$wrapped->populate(array('name' => 'John', 'username' => 'john.doe'));
		Assert::same('John', $user->name);
		Assert::same('john.doe', $user->username);
		Assert::false($wrapped->hasValidIdentifier());
		$this->em->persist($user);
		$this->em->flush();
		Assert::true($wrapped->hasValidIdentifier());
		Assert::type('array', $wrapped->getIdentifier());
		Assert::same($user->id, $wrapped->getSingleIdentifier());
		Assert::true($wrapped->isToManyAssociation('phoneNumbers'));
		Assert::false($wrapped->isToManyAssociation('address'));
		Assert::true($wrapped->isToOneAssociation('address'));
	}

	public function testInvalidAssociations()
	{
		$user = new CmsUser();
		$wrapped = $this->entityWrapper->wrap($user);
		$address = new CmsAddress('Prague');
		Assert::throws(function() use($wrapped, $address) {
			$wrapped->addToCollection('address', $address);
		}, '\Librette\Doctrine\InvalidAssociationException', '~^Class association .+ is not one-to-many or many-to-many association$~');

		$phoneNumber = new CmsPhoneNumber();
		Assert::throws(function() use($wrapped, $phoneNumber) {
			$wrapped->addToCollection('fooBar', $phoneNumber);
		}, '\Librette\Doctrine\InvalidAssociationException', '~^Class .+ has no association .+$~');

		Assert::throws(function () use ($wrapped, $phoneNumber) {
			$wrapped->addToCollection('articles', $phoneNumber);
		}, '\Librette\Doctrine\InvalidAssociationException', '~^Invalid class for .+\. .+ expected, .+ given$~');
	}

	public function testCollections()
	{
		$user = new CmsUser();
		$phoneNumbers = $user->phoneNumbers;
		$wrapped = $this->entityWrapper->wrap($user);
		$phoneNumber1 = new CmsPhoneNumber();
		$phoneNumber2 = new CmsPhoneNumber();
		$wrapped->addToCollection('phoneNumbers', $phoneNumber1);
		$wrapped->addToCollection('phoneNumbers', $phoneNumber2);
		Assert::same($user, $phoneNumber1->user);
		Assert::true($phoneNumbers->contains($phoneNumber1));
		Assert::true($phoneNumbers->contains($phoneNumber2));

		$wrapped->removeFromCollection('phoneNumbers', $phoneNumber2);
		Assert::null($phoneNumber2->user);
		Assert::true($phoneNumbers->contains($phoneNumber1));
		Assert::false($phoneNumbers->contains($phoneNumber2));

	}
	
	public function testCollectionWithoutCustomMethods()
	{
		$user = new CmsUser();
		$articles = $user->articles;
		$wrapped = $this->entityWrapper->wrap($user);
		$article1 = new CmsArticle();
		$article2 = new CmsArticle();
		$wrapped->addToCollection('articles', $article1);
		$wrapped->addToCollection('articles', $article2);
		Assert::same($user, $article1->user);
		Assert::true($articles->contains($article1));
		Assert::true($articles->contains($article2));

		$wrapped->removeFromCollection('articles', $article2);
		Assert::null($article2->user);
		Assert::true($articles->contains($article1));
		Assert::false($articles->contains($article2));
	}

	public function testGetSet()
	{
		$user = new CmsUser();
		$wrapped = $this->entityWrapper->wrap($user);
		$wrapped->setValue('username', 'Foo');
		Assert::equal('Foo', $wrapped->getRawValue('username'));
		Assert::equal('username: Foo', $wrapped->getValue('username'));

		$wrapped->setValue('name', 'foo');
		Assert::equal('xfoo', $wrapped->getRawValue('name'));
	}

	public function testScalarSet()
	{
		$user = new CmsUser('John');
		$this->em->persist($user);
		$this->em->flush();
		$article = new CmsArticle();
		$wrapped = $this->entityWrapper->wrap($article);
		$wrapped->setValue('user', $user->id);
		Assert::equal($user, $article->user);
	}

	public function testInvalidFields()
	{
		$user = new CmsUser();
		$wrapped = $this->entityWrapper->wrap($user);

		Assert::throws(function() use($wrapped) {
			$wrapped->setValue('foo', 'bar');
		}, 'Librette\Doctrine\InvalidFieldException', '~^Class .+ has no field .+$~');

		Assert::throws(function () use ($wrapped) {
			$wrapped->getValue('foo');
		}, 'Librette\Doctrine\InvalidFieldException', '~^Class .+ has no field .+$~');

		Assert::null($wrapped->tryGetValue('foo'));
		Assert::false($wrapped->trySetValue('foo', 'bar'));
	}

}

run(new WrappedEntityTestCase());