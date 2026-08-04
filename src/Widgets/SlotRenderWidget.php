<?php

namespace Flyo\Yii\Widgets;

use Flyo\Yii\Types\Accessor;
use yii\base\Widget;

class SlotRenderWidget extends Widget
{
    /**
     * @var object The slot to render, usually a `Flyo\Model\BlockSlotValue`. Any object which provides the
     * blocks of a slot in its `content` value is accepted, see [[Accessor::blocks()]].
     */
    public object $slot;

    public function run()
    {
        $content = '';
        foreach (Accessor::blocks($this->slot) as $block) {
            $content .= BlockWidget::widget(['block' => $block]);
        }
        return $content;
    }
}
