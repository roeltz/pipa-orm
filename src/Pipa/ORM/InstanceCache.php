<?php

namespace Pipa\ORM;
use DateTime;
use Pipa\Cache\MemoryCache;

class InstanceCache extends MemoryCache {
	
	function get($pk) {
		return parent::get($this->getKey($pk));
	}

	function has($pk) {
		return parent::has($this->getKey($pk));
	}
	
	function set($pk, $value) {
		return parent::set($this->getKey($pk), $value);
	}
	
	function remove($pk) {
		return parent::remove($this->getKey($pk));
	}
	
	static function getKey($pk) {
		if (is_array($pk)) {
			$values = array();
			foreach($pk as $v)
				$values[] = self::getKey($v);
			return join("+", $values);
		} elseif ($pk instanceof Entity) {
			return self::getKey(ORMHelper::getPKValues($pk));
		} elseif ($pk instanceof DateTime)
			return $pk->format("Y-m-d H:i:s");
		else
			return (string) $pk;
	}
}
