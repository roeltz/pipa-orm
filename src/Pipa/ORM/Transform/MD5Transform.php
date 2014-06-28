<?php

namespace Pipa\ORM\Transform;
use Pipa\ORM\Transform;

class MD5Transform implements Transform {
		
	function apply($value, $param) {
		return strlen($value) == 32 ? $value : md5($value);
	}
	
	function revert($value, $param) {
		return $value;
	}
}
