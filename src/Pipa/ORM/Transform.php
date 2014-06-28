<?php

namespace Pipa\ORM;

interface Transform {
	function apply($value, $param);
	function revert($value, $param);
}
