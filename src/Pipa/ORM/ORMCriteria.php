<?php

namespace Pipa\ORM;
use Pipa\Data\Aggregate;
use Pipa\Data\Criteria;
use Pipa\Data\CriteriaDecorator;
use Pipa\Data\Criterion;
use Pipa\Data\Restrictions;
use Pipa\ORM\Exception\NotFoundException;

class ORMCriteria extends CriteriaDecorator {

	public $bring;
	public $index;
	public $invocations = array();
	protected $descriptor;
	protected $mappingStrategy;

	function __construct(Criteria $criteria, Descriptor $descriptor, MappingStrategy $mappingStrategy) {
		parent::__construct($criteria);
		$this->descriptor = $descriptor;
		$this->mappingStrategy = $mappingStrategy;
		$mappingStrategy->initialize($this);
	}
	
	function __clone() {
		parent::__clone();
		if ($this->bring) {
			foreach($this->bring as &$bring) {
				$bring = clone $bring;
			}
		}
	}
	
	function add(Criterion $criterion) {
		if ($criterion instanceof Bring) {
			$this->bring($criterion);
		} else {
			parent::add($criterion);
		}
		return $this;
	}
	
	function bring($property, $_ = null) {
		$criteria = new Bring($property, $this);			
		$this->bring[] = $criteria;

		if ($_ === true) {
			return $criteria;
		} else {
			if ($args = array_slice(func_get_args(), 1))
				$criteria->addAll($args);
			return $this;
		}
	}
	
	function indexBy($property = null) {
		if (!$property)
			$property = $this->descriptor->pk;
		$this->index = (array) $property;
		return $this;
	}

	function invoke($method, array $args = array()) {
		$this->invocations[] = array($method, $args);
        return $this;
	}

	function getDescriptor() {
		return $this->descriptor;
	}

	function count() {
		$criteria = $this->mappingStrategy->expand($this);
		return $criteria->getCriteria()->count();
	}

    function aggregate(Aggregate $aggregate) {
        $criteria = $this->mappingStrategy->expand($this);
        return $criteria->getCriteria()->aggregate($aggregate);
    }

	function queryAll() {
		$criteria = $this->mappingStrategy->expand($this);
		$criteria->setDefaultOrder();
		return $this->processAll($criteria->getCriteria()->queryAll());
	}

	function queryField($field = null) {
		return $this->queryProperty($field ? $field : $this->fields[0], true);
	}

	function queryProperty($property, $raw = false) {
		$this->fields($this->descriptor->getBackendName($property));
		$criteria = $this->mappingStrategy->expand($this);
		$criteria->setDefaultOrder();
		$values = $criteria->getCriteria()->queryField();
		if (!$raw && $values && $this->descriptor->isRelationToOne($property)) {
			$class = $this->descriptor->one[$property]['class'];
			$pk = $class::getDescriptor()->pk[0];
			$values = $class::getCriteria()
				->where(Restrictions::in($pk, $values))
				->queryAll()
			;
		}
		return $values;
	}

	function querySingle() {
		$criteria = $this->mappingStrategy->expand($this);
		$criteria->setDefaultOrder();
		if ($record = $criteria->getCriteria()->querySingle()) {
			return $this->processSingle($record);
		}
	}

	function querySingleWithInstance(Entity $instance, $throw = true) {
		$criteria = $this->mappingStrategy->expand($this);
		$criteria->setDefaultOrder();
		if ($record = $criteria->getCriteria()->querySingle()) {
			return $this->processSingle($record, $instance);
		} elseif ($throw) {
			$class = get_class($instance);
			throw new NotFoundException("Entity of class $class not found");
		}
	}

	function queryValue() {
		throw new \Exception();
	}

	function update(array $values) {
		$criteria = $this->mappingStrategy->expand($this);
		$criteria->setDefaultOrder();
		return $criteria->getCriteria()->update($values);
	}

	protected function processAll(array $records) {
		$result = array();
		foreach($records as $record) {
			$result[] = $this->processSingle($record);
		}

		if ($this->index) {
			$indexed = array();
			foreach($result as $object) {
				$index = array();
				$record = $object->getEntityRecord();
				foreach($this->index as $property)
					$index[] = $record[$this->descriptor->getBackendName($property)];
				$indexed[InstanceCache::getKey($index)] = $object;
			}
			$result = $indexed;
		}

		return $result;
	}

	protected function processSingle(array $record, Entity $instance = null) {
		if (!$instance) {
			$class = $this->resolveClass($record);
			$instance = new $class;
		}
		$instance->cast($record);

		if ($this->bring) {
			foreach($this->bring as $bring) {
				$instance->bring(clone $bring);
			}
		}

		if ($this->invocations) {
			foreach($this->invocations as $invokation) {
				call_user_func_array(array($instance, $invokation[0]), $invokation[1]);
			}
		}
		
		return $instance;
	}

	protected function resolveClass(array $record) {
		return $this->mappingStrategy->resolveClass($this->descriptor, $record);
	}
	
	protected function setDefaultOrder() {
		if (!$this->order) {
			foreach($this->descriptor->orderByDefault as $field=>$type) {
				$this->orderBy($field, $type);
			}
		}
	}
}
