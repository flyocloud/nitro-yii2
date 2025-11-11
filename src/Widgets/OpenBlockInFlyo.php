<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\Block;
use yii\base\Widget;

class OpenBlockInFlyo extends Widget
{
    // Keep BC: the old class extends the new `Editable` implementation.
    // Emit a deprecation notice so maintainers can migrate to `Editable`.
    public function init()
    {
        @trigger_error(__CLASS__ . ' is deprecated and will be removed in a future release. Use Flyo\\Yii\\Widgets\\Editable instead.', E_USER_DEPRECATED);
        parent::init();
    }

    public static function attr(Block $block): string
    {
        @trigger_error(__METHOD__ . ' is deprecated. Use Flyo\\Yii\\Widgets\\Editable::attr instead.', E_USER_DEPRECATED);
        return Editable::attr($block);
    }
}
