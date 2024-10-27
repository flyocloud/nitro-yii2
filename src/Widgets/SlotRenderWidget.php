<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\BlockSlot;
use yii\base\Widget;

class SlotRenderWidget extends Widget
{
    public BlockSlot $slot;

    public function run()
    {
        $content = '';
        foreach ($this->slot->getContent() as $block) {
            $content .= BlockWidget::widget(['block' => $block]);
        }
        return $content;
    }
}
