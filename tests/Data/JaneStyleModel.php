<?php

namespace Flyo\Yii\Tests\Data;

/**
 * Behaves like a model of a generator which does not follow the openapi generator conventions, for example
 * jane. Such a model needs a [[\Flyo\Yii\Module::$modelHydrator]].
 */
class JaneStyleModel
{
    public ?string $headline = null;
}
