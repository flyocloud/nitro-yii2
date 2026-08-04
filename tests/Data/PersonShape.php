<?php

namespace Flyo\Yii\Tests\Data;

use Flyo\Yii\Types\Shape;

/**
 * Behaves like a generated type spec, see [[\Flyo\Yii\Types\TypeGenerator]].
 */
class PersonShape extends Shape
{
    public ?string $firstname = null;

    public ?int $age = null;

    public ?AddressShape $address = null;
}
