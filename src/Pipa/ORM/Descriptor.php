<?php

namespace Pipa\ORM;
use Pipa\Data\Field;
use Pipa\Importer\Importer;
use Pipa\Registry\Registry;
use Pipa\ORM\Annotation\Access;
use Pipa\ORM\Annotation\OrderByDefault;
use Pipa\ORM\Exception\DescriptorException;
use ReflectionClass;

class Descriptor {

	const ACCESS_TYPE_PROPERTY = 0;
	const ACCESS_TYPE_ACCESSOR = 1;
	const ACCESS_CONVENTION_CAMELCASE = 2;
	const ACCESS_CONVENTION_UNDERSCORE = 4;
	const EMBEDDED_PK = 1;
	const EMBEDDED_WHOLE = 2;
	const ENTITY_CLASS = 'Pipa\ORM\Entity';
	const EVENT_BEFORE_CAST = 1;
	const EVENT_AFTER_CAST = 2;
	const EVENT_BEFORE_SAVE = 1;
	const EVENT_AFTER_SAVE = 2;
	const EVENT_BEFORE_UPDATE = 3;
	const EVENT_AFTER_UPDATE = 4;
	const EVENT_BEFORE_DELETE = 5;
	const EVENT_AFTER_DELETE = 6;

	private $namespace;

	// Class-level data
	public $accessType = self::ACCESS_TYPE_PROPERTY;
	public $accessConvention;
	public $base;
	public $class;
	public $collection;
	public $dataSource = "default";
	public $discriminator;
	public $embeddedClass = false;
	public $parent;
	public $sequence;
	public $subclasses = array();

	// Property-level data
	// Array of property names
	public $cascaded = array();
	public $eager = array();
	public $generated = array();
	public $notNull = array();
	public $pk = array();
	public $persisted = array();

	// Array of property=>scalar
	public $backendNames = array();
	public $embedded = array();
	public $getters = array();
	public $eventHandlers = array();
	public $orderByDefault = array();
	public $setters = array();
	public $computed = array();

	// Array of property=>array
	public $many = array();
	public $one = array();
	public $transformed = array();

	static function getInstance($class, $context = "default") {
		$instance = Registry::getByClass(get_called_class(), "instances", "$class:$context");
		if (!$instance) {
			$factories = Registry::getAllByClass(get_called_class(), "factories");
			$defaultFactory = \Pipa\array_key_splice($factories, "default");

			foreach($factories as $namespace=>$factory) {
				if (Importer::isNamespace($class, $namespace)) {
					$instance = $factory->getInstance($class);
					break;
				}
			}

			if (!$instance) {
				$instance = $defaultFactory->getInstance($class);
			}

			Registry::setByClass(get_called_class(), "instances", "$class:$context", $instance);
		}
		return $instance;
	}

	static function registerDefaultFactory(DescriptorFactory $factory) {
		self::registerNamespaceFactory("default", $factory);
	}

	static function registerNamespaceFactory($namespace, DescriptorFactory $factory) {
		Registry::setByClass(get_called_class(), "factories", $namespace, $factory);
	}

	function __construct($class) {
		$this->class = $class;
		$this->namespace = preg_replace('/[^\\\\]+$/', '', $class);

		$parent = get_parent_class($class);
		if (is_subclass_of($parent, 'Pipa\ORM\Entity')) {
			$this->parent = self::getInstance($parent);
		}
	}

	function addAccessor($getter) {
		$setter = preg_replace('/^g/', 's', $getter);

		if (preg_match('/get_/', $getter)) {
			$words = array_slice(explode('_', $getter), 1);
		} else {
			$words = array_map('strtolower', array_slice(preg_split('/(?=[A-Z])/', $getter), 1));
		}

		if ($this->accessConvention == self::ACCESS_CONVENTION_UNDERSCORE) {
			$property = join('_', $words);
		} else {
			$property = join('', array_map('ucfirst', $words));
		}

		$this->getters[$property] = $getter;
		$this->setters[$property] = $setter;

		return $property;
	}

	function addCascaded($property) {
		$this->cascaded[] = $property;
	}

	function addComputed($property, $method) {
		$this->computed[$property] = $method;
	}

	function addEager($property) {
		$this->eager[] = $property;
	}

	function addEmbedded($property, $type) {
		$this->embedded[$property] = $type;
	}

	function addEventHandler($event, $method) {
		$this->eventHandlers[$event] = $method;
	}

	function addGenerated($property) {
		$this->generated[] = $property;
	}

	function addNotNull($property) {
		$this->notNull[] = $property;
	}

	function addOrderByDefault($property, $order) {
		$this->orderByDefault[$property] = $order;
	}

	function addPersisted($property) {
		$this->persisted[] = $property;
		if (!$this->hasBackendName($property))
			$this->setBackendName($property, $property);
	}

	function addPK($property) {
		$this->pk[] = $property;
	}

	function addRelationToMany($property, $class, $fk, $order = null, $where = null, $subproperty = null) {
		$class = $this->normalizeNamespace($class);
		$this->many[$property] = compact('class', 'fk', 'order', 'where', 'subproperty');
	}

	function addRelationToOne($property, $class, $fk = null) {
		if (!$fk) $fk = $property;
		$class = $this->normalizeNamespace($class);
		$this->one[$property] = compact('class', 'fk');
		if (count((array) $fk) == 1)
			$this->setBackendName($property, $fk);
	}

	function addSubclass($class, $discriminatorValue) {
		if ($this->discriminator) {
			if (is_string($discriminatorValue) || is_int($discriminatorValue)) {
				$this->subclasses[$discriminatorValue] = $this->normalizeNamespace($class);
			} else {
				throw new DescriptorException("Only strings and integers are allowed as discriminador value types");
			}
		} else {
			throw new DescriptorException("Class has no set discriminator. Set a discriminator before adding subclasses.");
		}
	}

	function addTransform($property, $name, $param) {
		$this->transformed[$property] = compact('name', 'param');
	}

	function getBackendName($property) {
		return isset($this->backendNames[$property]) ? $this->backendNames[$property] : $property;
	}

	function getBackendPK() {
		$pk = array();
		foreach($this->pk as $property) {
			$pk[] = $this->getBackendName($property);
		}
		return $pk;
	}

	function getGeneratedPK() {
		return current(array_intersect($this->pk, $this->generated));
	}

	function getPersistedFields() {
		$fields = array();
		foreach($this->persisted as $property) {
			$fields[$property] = new Field($this->getBackendName($property), $this->sourceCollection[$property]);
		}
		return $fields;
	}

	function getPropertyName($backendName) {
		return array_search($backendName, $this->backendNames);
	}

	function getRelationClass($property) {
		if ($class = @$this->one[$property]['class']);
		elseif ($class = @$this->many[$property]['class']);
		return $class;
	}

	function getTransformData($property) {
		return array_values($this->transformed[$property]);
	}

	function getSubclassesDescriptors() {
		$descriptors = array();
		foreach($this->subclasses as $discriminator=>$subclass) {
			$descriptors[$discriminator] = $descriptor = self::getInstance($subclass);
			$descriptors = array_merge($descriptors, $descriptor->getSubclassesDescriptors());
		}
		return $descriptors;
	}

	function hasBackendName($property) {
		return isset($this->backendNames[$property]);
	}

	function isCompatible(Descriptor $descriptor) {
		return
			$descriptor->accessType == $this->accessType
			&& $descriptor->accessConvention == $this->accessConvention
		;
	}

	function isCompound($property) {
		return isset($this->one[$property]) && is_array($this->one[$property]['fk']) && count($this->one[$property]['fk']) > 1;
	}

	function isCompoundPK() {
		return count($this->pk) > 1;
	}

	function isEager($property) {
		return in_array($property, $this->eager);
	}

	function isEmbedded($property) {
		return isset($this->embedded[$property]);
	}

	function isGenerated($property) {
		return in_array($property, $this->generated);
	}

	function isNotNull($property) {
		return in_array($property, $this->notNull);
	}

	function isRelationToMany($property) {
		return isset($this->many[$property]);
	}

	function isRelationToOne($property) {
		return isset($this->one[$property]);
	}

	function isTransformed($property) {
		return isset($this->transformed[$property]);
	}

	function needsInheritanceHandling() {
		return $this->base->subclasses;
	}

	function resolveParentDescriptor() {
		$class = new ReflectionClass($this->class);
		if ($parentClass = $class->getParentClass()) {
			if ($parentClass->getName() != self::ENTITY_CLASS) {
				$parentDescriptor = self::getInstance($parentClass->getName());
				if ($parentDescriptor && $parentDescriptor->isCompatible($this)) {
					$this->parent = $parentDescriptor;
					if ($parentDescriptor->base) {
						$this->base = $parentDescriptor->base;
					} else {
						$this->base = $parentDescriptor;
					}
					if (!$this->collection) {
						$this->collection = $parentDescriptor->collection;
					}
				} elseif (!$parentDescriptor) {
					$this->base = $this;
				}
			} else {
				$this->base = $this;
			}
		}
	}

	function resolveSourceCollections() {
		$class = new ReflectionClass($this->class);
		foreach($this->persisted as $property) {
			if ($this->accessType == self::ACCESS_TYPE_PROPERTY) {
				$reflector = $class->getProperty($property);
			} else {
				$reflector = $class->getMethod($this->getters[$property]);
			}

			$declaringClass = $reflector->getDeclaringClass()->getName();

			if ($declaringClass == $this->class) {
				$this->sourceCollection[$property] = $this->collection;
			} else {
				$otherDescriptor = self::getInstance($declaringClass);
				$this->sourceCollection[$property] = $otherDescriptor->collection;
			}
		}
	}

	function setAccess($type, $convention = null) {
		$this->accessType = $type;
		if ($type)
			$this->accessConvention = $convention;
	}

	function setBackendName($property, $name) {
		$this->backendNames[$property] = $name;
	}

	function setDiscriminator($property) {
		$this->discriminator = $property;
	}

	protected function normalizeNamespace($class) {
		if (!$this->namespace || strpos($class, '\\') === 0) {
			return $class;
		} else {
			return $this->namespace.$class;
		}
	}
}
