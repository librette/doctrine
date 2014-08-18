<?php
namespace Librette\Doctrine;

interface Exception
{

}


class RuntimeException extends \RuntimeException implements Exception
{

}


class InvalidFieldException extends RuntimeException
{

	public static function fieldNotExist($class, $field, $previous = NULL)
	{
		$class = is_object($class) ? get_class($class) : $class;

		return new static("Class $class has no field $field", NULL, $previous);
	}
}


class InvalidAssociationException extends RuntimeException implements Exception
{

	public static function notToManyAssociation($class, $association)
	{
		$class = is_object($class) ? get_class($class) : $class;

		return new static("Class association $class::\$$association is not one-to-many or many-to-many association");
	}


	public static function invalidTargetEntity($class, $association, $expected, $targetEntity)
	{
		$class = is_object($class) ? get_class($class) : $class;
		$targetClass = is_object($targetEntity) ? get_class($targetEntity) : $targetEntity;

		return new static("Invalid class for $class::\$$association. $expected expected, $targetClass given");
	}


	public static function associationNotExist($class, $association, $previous = NULL)
	{
		$class = is_object($class) ? get_class($class) : $class;

		return new static("Class $class has no association $association", NULL, $previous);
	}
}


class UnexpectedValueException extends \UnexpectedValueException implements Exception
{

	/**
	 * @param string|object $class
	 * @param string $property
	 *
	 * @return UnexpectedValueException
	 */
	public static function notACollection($class, $property)
	{
		$class = is_object($class) ? get_class($class) : $class;

		return new static("Class property $class::\$$property is not an instance of Doctrine\\Common\\Collections\\Collection.");
	}
}
