<?php

namespace Flyo\Yii\Widgets;

use Flyo\Yii\Module;
use Flyo\Yii\Types\Accessor;
use yii\base\InvalidConfigException;
use yii\base\Widget;

class BlockWidget extends Widget
{
    /**
     * @var object The block to render, usually a `Flyo\Model\Block`. Any object which provides the values of a
     * block is accepted, see [[Accessor::read()]], therefore a project can also render its own block models,
     * see [[\Flyo\Yii\Module::$blockModels]].
     */
    public object $block;

    public function run()
    {
        $module = Module::getInstance();
        $block = $module === null ? $this->block : $module->resolveBlockModel($this->block);

        $viewFile = Accessor::component($block);

        if (empty($viewFile)) {
            if (YII_DEBUG) {
                throw new InvalidConfigException("Block component name which is responsible for rendering the block is not set.");
            }

            return '';
        }

        return $this->render('@app/views/flyo/'. $viewFile, [
            'block' => $block,
            // the untyped json of the block, a view describes it with its generated type spec, see
            // [[\Flyo\Yii\Types\Shape]]
            'content' => Accessor::content($block),
            'config' => Accessor::config($block),
        ]);
    }
}
