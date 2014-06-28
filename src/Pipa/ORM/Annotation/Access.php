<?php

namespace Pipa\ORM\Annotation;
use Pipa\ORM\Descriptor;
use Pipa\ORM\Exception\DescriptorException;
use Pipa\Annotation\Annotation;

class Access extends Annotation {
	const PROPERTY = Descriptor::ACCESS_TYPE_PROPERTY;
	const ACCESSOR = Descriptor::ACCESS_TYPE_ACCESSOR;
	const CAMELCASE = Descriptor::ACCESS_CONVENTION_CAMELCASE;
	const UNDERSCORE = Descriptor::ACCESS_CONVENTION_UNDERSCORE;
	
	public $type;
	public $convention;
	
	function check($target = null) {
		if ($this->value && !$this->type)
			$this->type = $this->value;
		if ($this->type == self::PROPERTY && $this->convention)
			throw new DescriptorException("Cannot set access convention for property access");
	}
}
