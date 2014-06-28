<?php

namespace Pipa\ORM\MappingStrategy;
use Pipa\Data\Aggregate;
use Pipa\Data\Criteria;
use Pipa\Data\Expression;
use Pipa\Data\Expression\ComparissionExpression;
use Pipa\Data\Expression\JunctionExpression;
use Pipa\Data\Expression\ListExpression;
use Pipa\Data\Expression\SQLExpression;
use Pipa\Data\Field;
use Pipa\Data\Join;
use Pipa\Data\Order;
use Pipa\Data\Restrictions;
use Pipa\ORM\Descriptor;
use Pipa\ORM\Entity;
use Pipa\ORM\ORMCriteria;
use Pipa\ORM\ORMHelper;

class RelationalStrategy extends SimpleStrategy {

	protected $collectionCache;
	
	function expand(ORMCriteria $criteria, array &$optional = null) {
		$self = $this;
		$descriptor = $criteria->getDescriptor();
		$criteria = clone $criteria;
		$this->collectionCache = array();
		$expressions = array($criteria->expressions, $criteria->order, $optional);

		\Pipa\object_walk_recursive($expressions, function(&$c) use($self, $descriptor, &$criteria){

			if (($c instanceof Expression && !($c instanceof JunctionExpression || $c instanceof SQLExpression))
				|| $c instanceof Order
				|| $c instanceof Aggregate) {

				$operandName = isset($c->a) ? "a" : "field";

				list($field, $lastDescriptor, $joinFieldPairs) = $self->getElementsForPath($c->{$operandName}->name, $descriptor);

				$originalField = $c->{$operandName};
				$c->{$operandName} = $field;

				if ($c instanceof ComparissionExpression) {

					if ($c->b instanceof Field) {
						list($otherField, $otherLastDescriptor, $otherJoinFieldPairs) = $self->getElementsForPath($c->b->name, $descriptor);
						$c->b = $otherField;

						foreach($otherJoinFieldPairs as $joinFieldPair) {
							foreach($criteria->collection->joins as $join) {
								if ($join->collection == $joinFieldPair[1]->collection) {
									continue 2;
								}
							}
							$criteria->collection->join($joinFieldPair[1]->collection, Restrictions::eqf($joinFieldPair[0], $joinFieldPair[1]));
						}
					} elseif ($c->b instanceof Entity) {
						$pkValues = ORMHelper::getPKValues($c->b);
						if (count($pkValues) == 1) {
							$c->b = reset($pkValues);
						} else {
							$fk = array();
							$pkValues = array_values($pkValues);
							foreach((array) $lastDescriptor->one[$field->name]->fk as $i=>$k) {
								$fk[$k] = $pkValues[$i];
							}
							$c = Restrict::eqAll($fk);
						}
					}
				} elseif ($c instanceof ListExpression) {
					$values = array();
					foreach($c->values as $v) {
						if ($v instanceof Entity) {
							$pkval = ORMHelper::getPKValues($v);
							$v = reset($pkval);
						}
						$values[] = $v;
					}
					$c = new ListExpression($c->operator, $field, $values);
				} else {
					$c->field = $field;
				}
				
				if ($c instanceof Order)
					$joinType = Join::TYPE_LEFT;
				else
					$joinType = Join::TYPE_INNER;

				foreach($joinFieldPairs as $joinFieldPair) {
					foreach($criteria->collection->joins as $join) {
						if ($join->collection == $joinFieldPair[1]->collection) {
							continue 2;
						}
					}
					$criteria->collection->join($joinFieldPair[1]->collection, Restrictions::eqf($joinFieldPair[0], $joinFieldPair[1]), $joinType);
				}
			}

			return $c;

		});

		$criteria->collection->alias = "c1";
		$criteria->expressions = $expressions[0];
		$criteria->order = $expressions[1];
		$optional = $expressions[2];

		if (is_array($criteria->fields))
			foreach($criteria->fields as $field)
				if (!$field->collection || $field->collection->name == $criteria->collection->name)
					$field->collection = $criteria->collection;

		return $criteria;
	}

	function getCollection(Descriptor $descriptor, array $previous = array()) {
		$key = join(">>", array_merge(array_map(function($d){ return $d['descriptor']->class; }, $previous), array($descriptor->class)));
		if (!isset($this->collectionCache[$key])) {
			$this->collectionCache[$key] = clone $descriptor->collection;
			$this->collectionCache[$key]->alias = "c".count($this->collectionCache);
		}
		return $this->collectionCache[$key];
	}

	function getElementsForProperty($property, Descriptor $descriptor, array $previous = array()) {
		$field = new Field($descriptor->getBackendName($property), $this->getCollection($descriptor, $previous));
		return array($field, $descriptor);
	}

	function getElementsForPath($path, Descriptor $descriptor, array $previous = array(), array $joins = array()) {
		$components = explode(".", $path);
		if (count($components) == 1) {
			list($field) = $this->getElementsForProperty($path, $descriptor, $previous);
			if ($previous) {
				$prev = end($previous);
				$pk = $descriptor->getBackendPK();
				$joins[] = array($prev['field'], new Field(reset($pk), $this->getCollection($descriptor, $previous)));
			}
			return array($field, $descriptor, $joins);
		} else {
			list($field) = $this->getElementsForProperty($components[0], $descriptor, $previous);
			if (isset($descriptor->one[$components[0]])) {
				$nextDescriptor = Descriptor::getInstance($descriptor->one[$components[0]]['class']);
			} else {
				$nextDescriptor = $descriptor;
			}
			if ($previous) {
				$prev = end($previous);
				$joins[] = array($prev['field'], $field);
			} else {
				$pk = $nextDescriptor->getBackendPK();
				$joins[] = array($field, new Field(reset($pk), $this->getCollection($nextDescriptor, array(array('field'=>$field, 'descriptor'=>$descriptor)))));
			}
			$previous[] = array('field'=>$field, 'descriptor'=>$descriptor);
			return $this->getElementsForPath(join(".", array_slice($components, 1)), $nextDescriptor, $previous, $joins);
		}
	}
}
