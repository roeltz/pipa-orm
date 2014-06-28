<?php

namespace Pipa\ORM\Annotation;
use Pipa\Annotation\Annotation;

class Transform extends Annotation {
	public $name;
	public $param;
	
	function check($target = null) {
		if ($this->value && !$this->name)
			$this->name = $this->value;
	}
}
