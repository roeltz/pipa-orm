<?php

namespace Pipa\ORM;
use Pipa\ORM\DescriptorFactory\AnnotationFactory;

Descriptor::registerDefaultFactory(new AnnotationFactory());
ORMHelper::registerTransform('json', 'Pipa\ORM\Transform\JSONTransform');
ORMHelper::registerTransform('md5', 'Pipa\ORM\Transform\MD5Transform');
ORMHelper::registerTransform('sha1', 'Pipa\ORM\Transform\SHA1Transform');
ORMHelper::registerTransform('hash', 'Pipa\ORM\Transform\HashTransform');
