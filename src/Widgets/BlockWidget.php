<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\Block;
use yii\base\InvalidConfigException;
use yii\base\Widget;

class BlockWidget extends Widget
{
    public Block $block;

    public function run()
    {
        $viewFile = $this->block->getComponent();

        if (empty($viewFile)) {
            if (YII_DEBUG) {
                throw new InvalidConfigException("Block component name which is responsible for rendering the block is not set.");
            }

            return '';
        }

        return $this->render('@app/views/flyo/'. $this->block->getComponent(), [
            'block' => $this->block,
        ]);
    }
}
