<?php

namespace Pipa\ORM\Transform;

use Pipa\ORM\Descriptor;
use Pipa\ORM\Transform;

class HashTransform implements Transform {

	static function applyFromDescriptor($value, Descriptor $descriptor, $property) {
		$hash = new self();
		return $hash->apply($value, $descriptor->transformed[$property]["param"]);
	}
		
	function apply($value, $param) {
		if (preg_match('/^#/', $value)) {
			return $value;
		} else {
			$salt = @$param["salt"];
			$hash = hash($param["algo"], "{$salt}{$value}");
			return "#$hash";
		}
	}
	
	function revert($value, $param) {
		return $value;
	}
}
