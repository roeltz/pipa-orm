<?php

namespace Pipa\ORM\Annotation;
use Pipa\Annotation\Annotation;
use Pipa\Data\Order;

class OrderByDefault extends Annotation {
	public $value = Order::TYPE_ASC;
}
