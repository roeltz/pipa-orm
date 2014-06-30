<?php

namespace Pipa\ORM;
use Pipa\Data\ConnectionManager;
use Pipa\Data\MultipleInsertionSupport;
use Pipa\Data\HasPrimaryKey;
use Pipa\Data\Restrictions;
use Pipa\Data\RelationalCriteria;
use Pipa\ORM\MappingStrategy\SimpleStrategy;
use Pipa\ORM\MappingStrategy\RelationalStrategy;
use Pipa\ORM\MappingStrategy\NormalizedInheritanceStrategy;

abstract class Entity implements HasPrimaryKey {

	static $strategies = array();
	static $instanceCache = array();
	static $batchInsert = array();
	static $namespacedDataSources = array();
	private $record;

	static function find($_ = null) {
		return self::getCriteria()
			->addAll(func_get_args())
			->queryAll()
		;
	}

	static function getCriteria() {
		return new ORMCriteria(
			self::getDataSource()->getCriteria(),
			self::getDescriptor(),
			self::getMappingStrategy()
		);
	}

	static function getDataSource() {
		foreach(self::$namespacedDataSources as $ns=>$ds) {
			if (strpos(get_called_class(), "$ns\\") === 0)
				return ConnectionManager::get($ds);
		}
		return ConnectionManager::get(self::getDescriptor()->dataSource);
	}

	static function getDescriptor() {
		return Descriptor::getInstance(get_called_class());
	}

	static function getInstanceCache() {
		if (!isset(self::$instanceCache[$class = get_called_class()])) {
			self::$instanceCache[$class] = new InstanceCache();
		}
		return self::$instanceCache[$class];
	}

	static function getMappingStrategy() {
		if (!isset(self::$strategies[$class = get_called_class()])) {
			if (self::getDataSource()->getCriteria() instanceof RelationalCriteria) {
				/*if (self::getDescriptor()->parent) {
					self::$strategies[$class] = new NormalizedInheritanceStrategy();
				} else {*/
					self::$strategies[$class] = new RelationalStrategy();
				//}
			} else {
				self::$strategies[$class] = new SimpleStrategy();
			}
		}
		return self::$strategies[$class];
	}
	
	static function setNamespaceDataSource($ns, $dataSourceName) {
		self::$namespacedDataSources[$ns] = $dataSourceName;
		uksort(self::$namespacedDataSources, function($a, $b){
			return strlen($b) - strlen($a);
		});
	}

	function __construct($_ = null) {
		ORMHelper::unsetBrought($this);
		if (func_num_args() > 0)
			$this->retrieve(func_get_args());
	}

	function __get($property) {
		if ($this->getDescriptor()->isRelationToOne($property)
			|| $this->getDescriptor()->isRelationToMany($property)) {
			return $this->bring($property);
		} else {
			return $this->__getComputed($property);
		}
	}

	function __getComputed($property) {
	}
	
	function getPrimaryKeyValue() {
		return current(ORMHelper::getPKValues($this));
	}

	function bring($property, $_ = null) {
		$bring = $property instanceof Bring ? $property : new Bring($property, $this->getCriteria());
		$bring->addAll(is_array($_) ? $_ : array_slice(func_get_args(), 1));
		return $this->{$bring->property} = ORMHelper::bring($this, $bring);
	}
	
	function cast(array $record) {
		list($values, $innerRecord, $bring, $unset) = ORMHelper::getCastInformation(self::getDescriptor(), $record);

		$descriptor = $this->getDescriptor();
		$this->record = $innerRecord;

		foreach($values as $property=>$value)
			ORMHelper::setProperty($this, $property, $value);

		foreach($unset as $property)
			unset($this->$property);

		foreach($bring as $property)
			$this->bring($property);

		ORMHelper::setComputed($this);

		$this->getInstanceCache()->set($this, $this);
	}

	function retrieve($pk) {
		if (!is_array($pk))
			$pk = func_get_args();

		if (!\Pipa\is_assoc($pk))
			$pk = ORMHelper::buildPK($this->getDescriptor(), $pk);

		$criteria = $this->getCriteria()->where(Restrictions::eqAll($pk));
		$record = $criteria->querySingleWithInstance($this);
		return $this;
	}

	function refresh() {
		$this->retrieve(ORMHelper::getPKValues($this));
		return $this;
	}

	function save() {
		$class = get_called_class();
		if (isset(self::$batchInsert[$class])) {
			self::$batchInsert[$class][] = $this;
		} else {
			$descriptor = Descriptor::getInstance($class);
			$generatedPK = self::getMappingStrategy()->save($this);
			if ($generatedPK && ($property = $descriptor->getGeneratedPK())) {
				ORMHelper::setProperty($this, $property, $generatedPK);
			}
			$this->refresh();
			ORMHelper::setComputed($this);
		}
	}

	static function beginSave() {
		if (self::getDataSource() instanceof MultipleInsertionSupport) {
			self::$batchInsert[get_called_class()] = array();
		}
	}

	static function commitSave() {
		if ($entities = @self::$batchInsert[get_called_class()]) {
			self::getMappingStrategy()->saveMultiple($entities);
		}
		unset(self::$batchInsert[get_called_class()]);
	}

	static function abortSave() {
		unset(self::$batchInsert[get_called_class()]);
	}

	function update() {
		self::getMappingStrategy()->update($this);
		ORMHelper::setComputed($this);
	}

	function delete() {
		$descriptor = self::getDescriptor();
		foreach($descriptor->cascaded as $property) {
			$items = $this->bring($property);
			if ($descriptor->isRelationToOne($items)) {
				$items->delete();
			} else {
				foreach($items as $item) {
					$item->delete();
				}
			}
		}
		self::getMappingStrategy()->delete($this);
	}

	function getInstanceCriteria() {
		return self::getCriteria()->where(Restrictions::eqAll(ORMHelper::getPKValues($this)));
	}

	function getEntityRecord() {
		return $this->record;
	}
}
