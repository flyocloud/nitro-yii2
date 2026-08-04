<?php

namespace Flyo\Yii\Tests\Data;

use RuntimeException;

/**
 * A model which fails while it is hydrated, this happens when the models have not been regenerated after a
 * block schema has changed.
 */
class BrokenModel extends HeroModel
{
    public function setUid(?string $uid): self
    {
        throw new RuntimeException('The uid is not valid.');
    }
}
