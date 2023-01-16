<?php

namespace Pipa\ORM;

interface MappingStrategy {
	function save(Entity $entity);
	function saveMultiple(array $entities);
	function update(Entity $entity, $allFields = false);
	function delete(Entity $entity);

	function expand(ORMCriteria $criteria, array &$optional = null);
	function initialize(ORMCriteria $criteria);

	function resolveClass(Descriptor $descriptor, array $record);
}
