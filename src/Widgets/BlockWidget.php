<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\Block;
use yii\base\Widget;

class BlockWidget extends Widget
{
    public Block $block;

    public function render($view, $params = [])
    {
        return $this->render('@app/flyo/'. $this->block->getComponent(), [
            'block' => $this->block,
        ]);
    }
}