<?php

namespace Pipa\ORM\MappingStrategy;
use Pipa\ORM\Descriptor;
use Pipa\ORM\ORMCriteria;
use Pipa\Data\Collection;
use Pipa\Data\JoinableCollection;
use Pipa\Data\Restrictions;

class NormalizedInheritanceStrategy extends RelationalStrategy {

	function save(Entity $entity) {
		
	}
		
	function expand(ORMCriteria $criteria, array &$optional = null) {
		$criteria = parent::expand($criteria);
		
		return $criteria;
	}
	
	function initialize(ORMCriteria $criteria) {
		$descriptor = $criteria->getDescriptor();
		$collection = $this->resolveCollection($descriptor);
		$fields = $this->resolveFields($descriptor, $collection);
		$criteria->from($collection)->fields($fields);
	}
	
	function resolveCollection(Descriptor $descriptor) {
		$collection = JoinableCollection::from($descriptor->collection);
		$currentDescriptor = $descriptor->parent;
		
		while($currentDescriptor) {
			$currentCollection = clone $currentDescriptor->collection;
			if ($descriptor->isCompoundPK()) {
				$on = array();
				foreach($descriptor->pk as $property) {
					$on[] = Restrictions::eqf(
						$collection->field($property),
						$currentCollection->field($property)
					);
				}
				$on = Restrictions::_and($on);
			} else {
				$on = Restrictions::eqf(
					$collection->field($descriptor->pk[0]),
					$currentCollection->field($currentDescriptor->pk[0])
				);
			}
			$collection->join($currentCollection, $on);
			$currentDescriptor = $currentDescriptor->parent;
		}
		
		foreach($descriptor->getSubclassesDescriptors() as $currentDescriptor) {
			$currentCollection = clone $currentDescriptor->collection;
			if ($descriptor->isCompoundPK()) {
				$on = array();
				foreach($descriptor->pk as $property) {
					$on[] = Restrictions::eqf(
						$collection->field($property),
						$currentCollection->field($property)
					);
				}
				$on = Restrictions::_and($on);
			} else {
				$on = Restrictions::eqf(
					$collection->field($descriptor->pk[0]),
					$currentCollection->field($currentDescriptor->pk[0])
				);
			}
			$collection->leftJoin($currentCollection, $on);
		}
		return $collection;
	}

	protected function resolveFields(Descriptor $descriptor, JoinableCollection $collection) {
		$fields = $descriptor->getPersistedFields();
		$collections = array($collection);
		foreach($collection->joins as $join) {
			$collections[] = $join->collection;
		}
		foreach($fields as $field) {
			foreach($collection as $collection) {
				if ($field->collection->name == $collection) {
					$field->collection = $collection;
				}
			}
		}
		return $fields;
	}
}
