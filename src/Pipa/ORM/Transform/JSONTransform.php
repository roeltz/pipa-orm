<?php

namespace Pipa\ORM\Transform;
use Pipa\ORM\Transform;

class JSONTransform implements Transform {
		
	function apply($value, $param) {
		return json_encode($value);
	}
	
	function revert($value, $param) {
		return json_decode($value);
	}
}
