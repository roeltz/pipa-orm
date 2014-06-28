<?php

namespace Pipa\ORM\DescriptorFactory;
use InvalidArgumentException;
use Pipa\Annotation\Reader;
use Pipa\Data\Collection;
use Pipa\ORM\Descriptor;
use Pipa\ORM\DescriptorFactory;
use Pipa\ORM\Exception\DescriptorException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class AnnotationFactory implements DescriptorFactory {

	const ENTITY_CLASS = Descriptor::ENTITY_CLASS;

	function getInstance($class) {
		$class = new ReflectionClass($class);
		$descriptor = new Descriptor($class->getName());
		$reader = new Reader('Pipa\ORM\Annotation');

		$this->surveyClass($descriptor, $reader, $class->getName());

		if ($descriptor->accessType) {
			foreach($class->getMethods() as $method) {
				if ($method->isPublic() && $method->getDeclaringClass()->getName() != self::ENTITY_CLASS) {
					$this->surveyMethod($descriptor, $reader, $class->getName(), $method->getName());
				}
			}
		} else {
			foreach($class->getProperties() as $property) {
				if ($property->isPublic() && !$property->isStatic()) {
					$this->surveyProperty($descriptor, $reader, $class->getName(), $property->getName());
				}
			}
		}

		$descriptor->resolveSourceCollections();

		return $descriptor;
	}

	protected function surveyClass(Descriptor $descriptor, Reader $reader, $class) {
		if ($collection = $reader->getClassAnnotation($class, 'Collection'))
			$descriptor->collection = Collection::from($collection->value);
		elseif ($embedded = $reader->getClassAnnotation($class, 'Embedded')) {
			$descriptor->embeddedClass = true;
		} else {
			throw new DescriptorException("Class does not have an assigned collection nor is declared as embedded");
		}

		$descriptor->resolveParentDescriptor();

		if ($access = $reader->getClassAnnotation($class, 'Access')) {
			$descriptor->setAccess($access->type, $access->convention);
		} elseif ($descriptor->parent) {
			$descriptor->setAccess($descriptor->parent->accessType, $descriptor->parent->accessConvention);
		}

		if ($dataSource = $reader->getClassAnnotation($class, 'DataSource')) {
			$descriptor->dataSource = $dataSource->value;
		} elseif($descriptor->parent) {
			$descriptor->dataSource = $descriptor->parent->dataSource;
		}

		if ($discriminator = $reader->getClassAnnotation($class, 'Discriminator')) {
			$descriptor->setDiscriminator($discriminator);
		} elseif ($descriptor->parent && $descriptor->parent->discriminator) {
			$descriptor->setDiscriminator($descriptor->parent->discriminator);
		}

		if ($subclasses = $reader->getClassAnnotations($class, 'Subclass')) {
			foreach($subclasses as $subclass) {
				$descriptor->addSubclass($subclass->class, $subclass->discriminatorValue);
			}
		}
	}

	protected function surveyMethod(Descriptor $descriptor, Reader $reader, $class, $method) {
		// Looks like an accessor
		if (preg_match('/^get/', $method)) {
			$property = $descriptor->addAccessor($method);
			$this->surveyMember($descriptor, $reader, 'method', $class, $method, $property);
		// Looks like something to put an event handler on
		} elseif ($on = $reader->getMethodAnnotations($class, $method, 'On')) {
			foreach($on as $a) {
				$descriptor->addEventHandler($a->value, $method);
			}
		}
	}

	protected function surveyProperty(Descriptor $descriptor, Reader $reader, $class, $property) {
		$this->surveyMember($descriptor, $reader, 'property', $class, $property, $property);
	}

	protected function surveyMember(Descriptor $descriptor, Reader $reader, $memberType, $class, $member, $property) {

		$readerMethod = 'get'.ucfirst($memberType).'Annotation';

		if (call_user_func(array($reader, $readerMethod), $class, $member, 'Ignore'))
			return;

		$computed = call_user_func(array($reader, $readerMethod), $class, $member, 'Computed');

		if ($computed) {
			$descriptor->addComputed($property, $computed->value);
			return;
		}

		if ($aliasOf = call_user_func(array($reader, $readerMethod), $class, $member, 'AliasOf'))
			$descriptor->setBackendName($property, $aliasOf->value);

		if (call_user_func(array($reader, $readerMethod), $class, $member, 'Cascaded'))
			$descriptor->addCascaded($property);

		if (call_user_func(array($reader, $readerMethod), $class, $member, 'Eager'))
			$descriptor->addEager($property);

		if ($embedded = call_user_func(array($reader, $readerMethod), $class, $member, 'Embedded'))
			$descriptor->addEmbedded($property, $embedded->value);

		if ($generated = call_user_func(array($reader, $readerMethod), $class, $member, 'Generated')) {
			$descriptor->addGenerated($property);
			if ($generated->value)
				$descriptor->sequence = $generated->value;
		}

		if (call_user_func(array($reader, $readerMethod), $class, $member, 'Id'))
			$descriptor->addPK($property);

		if (call_user_func(array($reader, $readerMethod), $class, $member, 'NotNull'))
			$descriptor->addNotNull($property);

		if ($order = call_user_func(array($reader, $readerMethod), $class, $member, 'OrderByDefault'))
			$descriptor->addOrderByDefault($property, $order->value);

		if ($many = call_user_func(array($reader, $readerMethod), $class, $member, 'Many'))
			$descriptor->addRelationToMany($property, $many->class, $many->fk, $many->order, $many->where);

		if ($one = call_user_func(array($reader, $readerMethod), $class, $member, 'One'))
			$descriptor->addRelationToOne($property, $one->class, $one->fk);

		if ($transform = call_user_func(array($reader, $readerMethod), $class, $member, 'Transform'))
			$descriptor->addTransform($property, $transform->name, $transform->param);

		if (!$many || $embedded)
			$descriptor->addPersisted($property);
	}
}