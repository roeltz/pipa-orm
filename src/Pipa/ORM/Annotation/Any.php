<?php

namespace Pipa\ORM\Annotation;
use Pipa\Annotation\Annotation;

class Any extends Annotation {
	public $class;
	public $fk;
	public $property;
	public $value;
	public $lazy = true;
}
