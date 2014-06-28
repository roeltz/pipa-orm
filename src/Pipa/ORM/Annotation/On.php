<?php

namespace Pipa\ORM\Annotation;
use Pipa\Annotation\Annotation;
use Pipa\ORM\Descriptor;

class On extends Annotation {
	const BEFORE_CAST = Descriptor::EVENT_BEFORE_CAST;
	const AFTER_CAST = Descriptor::EVENT_AFTER_CAST;
	const BEFORE_SAVE = Descriptor::EVENT_BEFORE_SAVE;
	const AFTER_SAVE = Descriptor::EVENT_AFTER_SAVE;
	const BEFORE_UPDATE = Descriptor::EVENT_BEFORE_UPDATE;
	const AFTER_UPDATE = Descriptor::EVENT_AFTER_UPDATE;
	const BEFORE_DELETE = Descriptor::EVENT_BEFORE_DELETE;
	const AFTER_DELETE = Descriptor::EVENT_AFTER_DELETE;
}
