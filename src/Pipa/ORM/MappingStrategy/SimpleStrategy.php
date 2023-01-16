<?php

namespace Pipa\ORM\MappingStrategy;
use Pipa\ORM\Descriptor;
use Pipa\ORM\Entity;
use Pipa\ORM\MappingStrategy;
use Pipa\ORM\ORMCriteria;
use Pipa\ORM\ORMHelper;

class SimpleStrategy implements MappingStrategy {
		
	function save(Entity $entity) {
		return $entity->getDataSource()->save(
			ORMHelper::getPersistedValues($entity),
			$entity->getDescriptor()->collection,
			$entity->getDescriptor()->sequence
		);
	}
	
	function saveMultiple(array $entities) {
		if ($entities) {
			$dataSource = $entities[0]->getDataSource();
			$collection = $entities[0]->getDescriptor()->collection;
			foreach($entities as &$entity) {
				$entity = ORMHelper::getPersistedValues($entity);
			}
			return $dataSource->saveMultiple($entities, $collection);
		}
	}
	
	function update(Entity $entity, $allFields = false) {
		$values = ORMHelper::getPersistedValues($entity, true);

		if (!$allFields) {
			$record = $entity->getEntityRawRecord();
			$diff = [];

			foreach ($values as $k=>$v) {
				if ($v !== $record[$k]) {
					$diff[$k] = $v;
				}
			}

			$values = $diff;
		}

		$entity->getDataSource()->update(
			$values,
			$entity->getInstanceCriteria()
		);
	}
	
	function delete(Entity $entity) {
		$entity->getDataSource()->delete($entity->getInstanceCriteria());
	}
	
	function expand(ORMCriteria $criteria, array &$optional = null) {
		return $criteria;
	}

	function initialize(ORMCriteria $criteria) {
		$criteria
			->from($criteria->getDescriptor()->collection)
			->fields($criteria->getDescriptor()->getPersistedFields());
	}
	
	function resolveClass(Descriptor $descriptor, array $record) {
		return $descriptor->class;
	}
}
