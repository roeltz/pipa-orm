<?php

namespace Pipa\ORM\Transform;
use Pipa\ORM\Transform;

class HashTransform implements Transform {
		
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
