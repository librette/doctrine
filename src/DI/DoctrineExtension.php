<?php
namespace Librette\Doctrine\DI;

use Nette\DI\CompilerExtension;

/**
 * @author David Matejka
 */
class DoctrineExtension extends CompilerExtension
{

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('entityWrapper'))
				->setClass('Librette\Doctrine\EntityWrapper');

		$builder->addDefinition($this->prefix('metadataReader'))
				->setClass('\Librette\Doctrine\MetadataReader');
	}
}
