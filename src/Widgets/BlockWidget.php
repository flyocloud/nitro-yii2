<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\Block;
use yii\base\Widget;

class BlockWidget extends Widget
{
    public Block $block;

    public function run()
    {
        return $this->render('@app/views/flyo/'. $this->block->getComponent(), [
            'block' => $this->block,
        ]);
    }
}
