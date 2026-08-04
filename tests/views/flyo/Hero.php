<?php

/**
 * View file of the `Hero` block, used by [[\Flyo\Yii\Tests\BlockWidgetTest]].
 *
 * @var object $block The block, see [[\Flyo\Yii\Widgets\BlockWidget]].
 * @var mixed $content The untyped content of the block.
 * @var mixed $config The untyped config of the block.
 */

use Flyo\Yii\Accessor;

echo implode('|', [
    'type:' . get_debug_type($block),
    'uid:' . Accessor::uid($block),
    'headline:' . ($content->headline ?? ''),
    'variant:' . ($config->variant ?? ''),
]);
