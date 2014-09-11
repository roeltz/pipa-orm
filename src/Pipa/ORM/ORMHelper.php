<?php

namespace Pipa\ORM;
use Pipa\Data\Restrictions;
use Pipa\ORM\Exception\PropertyValueException;
use Pipa\Registry\Registry;

abstract class ORMHelper {

	static function bring(Entity $object, Bring $criteria) {
		$criteria = clone $criteria;
		$descriptor = $object->getDescriptor();
		$record = $object->getEntityRecord();
		$value = null;

		if ($descriptor->isEmbedded($criteria->property)) {
			return self::bringEmbedded($object, $criteria->property);
		}
		
		if ($descriptor->isRelationToOne($criteria->property)) {
			$one = $descriptor->one[$criteria->property];
			$otherDescriptor = $one['class']::getDescriptor();

			$worthy = false;
			$pk = array();
			$lk = self::getPKFields($otherDescriptor);
			foreach((array) $one['fk'] as $i=>$field) {
				if ($pkv = @$record[$field]) {
					$pk[$lk[$i]] = $pkv;
					$worthy = true;
				}
			}

			if ($worthy) {
				if ($instance = $one['class']::getInstanceCache()->get($pk)) {
					if ($criteria->bring) {
						$instance = clone $instance;
						foreach($criteria->bring as $bring) {
							$instance->bring($bring);
						}
					}
					return $instance;
				} else {
					$value = $criteria
						->where(Restrictions::eqAll($pk))
						->querySingle()
					;
				}
			}

			return $value;

		} elseif ($descriptor->isRelationToMany($criteria->property)) {
			$many = $descriptor->many[$criteria->property];
			$otherDescriptor = $many['class']::getDescriptor();

			$fk = array();
			$lk = self::getPKFields($descriptor);
			foreach((array) $many['fk'] as $i=>$field) {
				$fk[$field] = $record[$lk[$i]];
			}

			if (!$criteria)
				$criteria = $many['class']::getCriteria();
			
			if ($many['where']) {
				$where = $many['where'];
				foreach($where as $p=>&$v) {
					if ($v === "this") {
						$v = $object;
					}
				}
				$criteria->where(Restrictions::eqAll($where));
			}

			$criteria->where(Restrictions::eqAll($fk));
			
			if ($subproperty = $many['subproperty']) {
				$value = $criteria->queryProperty($subproperty);
			} else {
				$value = $criteria->queryAll();
			}

			return $value;
		}
	}

	static function bringEmbedded(Entity $object, $property) {
		$descriptor = $object->getDescriptor();
		$record = $object->getEntityRecord();
		$value = $record[$descriptor->getBackendName($property)];

		if ($descriptor->isRelationToOne($property)) {
			$instance = new $descriptor->one[$property]['class'];
			$instance->cast((array) $value);
			return $instance;
		} elseif ($descriptor->isRelationToMany($property)) {
			$instances = array();
			foreach($value as $subrecord) {
				$instance = new $descriptor->many[$property]['class'];
				$instance->cast((array) $value);
				$instances[] = $instance;
			}
			return $instances;
		}
	}

	static function buildPK(Descriptor $descriptor, array $args) {
		if (is_array($args[0])) {
			$pk = $args[0];
		} else {
			$pk = array();
			foreach($descriptor->pk as $i=>$property) {
				$pk[$property] = $args[$i];
			}
		}
		return $pk;
	}

	static function getPKFields(Descriptor $descriptor) {
		$fields = array();
		foreach($descriptor->pk as $p) {
			if ($descriptor->isCompound($p)) {
				$fields = array_merge($fields, $descriptor->one[$p]['fk']);
			} else {
				$fields[] = $descriptor->getBackendName($p);
			}
		}
		return $fields;
	}

	static function getCastInformation(Descriptor $descriptor, array $record, array $properties = null, $partial = false) {
		$values = array();
		$innerRecord = array();
		$bring = array();
		$unset = array();

		if (!$properties) $properties = $descriptor->persisted;

		foreach($properties as $property) {
			$set = true;
			$one = @$descriptor->one[$property];
			$many = @$descriptor->many[$property];
			$compound = $descriptor->isCompound($property);
			$eager = $descriptor->isEager($property);
			$embedded = @$descriptor->embedded[$property];
			$transform = @$descriptor->transformed[$property];

			if ($compound) {
				$value = array();
				foreach($one['fk'] as $field) {
					$innerRecord[$field] = $value[$field] = $record[$field];
				}
			} elseif (!$many) {
				$field = $descriptor->getBackendName($property);
				$value = $record[$field];
			}

			if (!$compound) {
				if ($transform) {
					$value = ORMHelper::getTransformInstance($transform['name'])->revert($value, $transform['param']);
				}
				$innerRecord[$field] = $value;
			}

			if (!$partial && ($one || $many)) {
				$set = false;
				if ($eager || $embedded == Descriptor::EMBEDDED_WHOLE) {
					$bring[] = $property;
				}
			}

			if ($set) {
				$values[$property] = $value;
			} else {
				$unset[] = $property;
			}
		}

		return array($values, $innerRecord, $bring, $unset);
	}

	static function getPersistedValues(Entity $object, $isUpdate = false, array $properties = null) {
		$values = array();
		$descriptor = $object->getDescriptor();
		if (!$properties) $properties = $descriptor->persisted;
		foreach($properties as $property) {
			if (!$descriptor->isRelationToMany($property)
				&& ($isUpdate || !$descriptor->isGenerated($property))) {
				$value = self::getProperty($object, $property);

				if (is_null($value) && $descriptor->isNotNull($property)) {
					throw new PropertyValueException("Property {$descriptor->class}::{$property} is null");
				}

				if ($descriptor->isEmbedded($property)) {
					$value = ORMHelper::getPersistedValues($value);
				}

				$values[$property] = $value;
			}
		}
		$rawValues = array();
		foreach($values as $property=>$value) {
			if ($descriptor->isCompound($property)) {
				$fk = $descriptor->one[$property]['fk'];
				$fkValues = self::getPKValues($value);
				foreach($fk as $i=>$field) {
					$rawValues[$field] = $fkValues[$i];
				}
				continue;
			} elseif ($value instanceof Entity) {
				$value = current(self::getPKValues($value));
			}

			if ($descriptor->isTransformed($property)) {
				list($tname, $tparam) = $descriptor->getTransformData($property);
				$value = self::getTransformInstance($tname)->apply($value, $tparam);
			}

			$rawValues[$descriptor->getBackendName($property)] = $value;
		}
		return $rawValues;
	}

	static function getPKValues(Entity $object) {
		return self::getPersistedValues($object, true, $object->getDescriptor()->pk);
	}

	static function getProperty(Entity $object, $property) {
		$descriptor = $object->getDescriptor();
		if ($descriptor->accessType == Descriptor::ACCESS_TYPE_PROPERTY) {
			return $object->$property;
		} else {
			return call_user_func(array($object, $descriptor->getters[$property]));
		}
	}

	static function getTransformInstance($name) {
		return Registry::getByClass(get_called_class(), "transform", $name);
	}

	static function registerTransform($name, $class) {
		Registry::setSingletonByClass(get_called_class(), "transform", $name, function() use($class){
			return new $class;
		});
	}

	static function setComputed(Entity $object) {
		foreach($object->getDescriptor()->computed as $property=>$method) {
			self::setProperty($object, $property, call_user_func(array($object, $method)));
		}
	}

	static function setProperty(Entity $object, $property, $value) {
		$descriptor = $object->getDescriptor();
		if ($descriptor->accessType == Descriptor::ACCESS_TYPE_PROPERTY) {
			$object->$property = $value;
		} else {
			call_user_func(array($object, $descriptor->setters[$property]), $value);
		}
	}

	static function unsetBrought(Entity $object) {
		$descriptor = $object->getDescriptor();
		foreach($descriptor->one as $property=>$x)
			unset($object->$property);
		foreach($descriptor->many as $property=>$x)
			unset($object->$property);
	}
}
