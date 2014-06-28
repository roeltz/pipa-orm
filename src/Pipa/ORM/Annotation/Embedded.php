<?php

namespace Pipa\ORM\Annotation;
use Pipa\Annotation\Annotation;
use Pipa\ORM\Descriptor;

class Embedded extends Annotation {
	const PK = Descriptor::EMBEDDED_PK;
	const WHOLE = Descriptor::EMBEDDED_WHOLE;
	public $value = self::WHOLE;
}
