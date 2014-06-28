<?php

namespace Pipa\ORM;
use Pipa\Data\Criterion;

class Bring extends ORMCriteria implements Criterion {
	private $parent;
	public $property;
	
	function __construct($property, ORMCriteria $parent = null) {
		$this->property = $property;
		$this->parent = $parent;
		$broughtClass = $parent->getDescriptor()->getRelationClass($property);
		parent::__construct(
			$broughtClass::getDataSource()->getCriteria(),
			$broughtClass::getDescriptor(),
			$broughtClass::getMappingStrategy()
		);
	}

	function done() {
		return $this->parent;
	}
}
