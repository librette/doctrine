<?php
namespace Librette\Doctrine\Annotations;

/**
 * @author David Matejka
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
class ManipulateMethods
{

	/** @var string */
	public $add;

	/** @var string */
	public $remove;

	/** @var string */
	public $set;

	/** @var string */
	public $get;

}
