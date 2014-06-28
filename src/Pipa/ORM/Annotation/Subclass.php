<?php

namespace Pipa\ORM\Annotation;
use Pipa\Annotation\Annotation;

class Subclass extends Annotation {
	public $class;
	public $discriminatorValue;
}
