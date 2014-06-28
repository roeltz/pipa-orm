<?php

namespace Pipa\ORM\Transform;
use Pipa\ORM\Transform;

class SHA1Transform implements Transform {
		
	function apply($value, $param) {
		return strlen($value) == 40 ? $value : sha1($value);
	}
	
	function revert($value, $param) {
		return $value;
	}
}
