<?php

namespace Flyo\Yii\Widgets;

use Flyo\Yii\Accessor;
use yii\base\Widget;

class PageWidget extends Widget
{
    /**
     * @var object The page to render, usually a `Flyo\Model\Page`. Any object which provides the blocks of a
     * page in its `json` value is accepted, see [[Accessor::blocks()]].
     */
    public object $page;

    public function run()
    {
        $content = '';
        foreach (Accessor::blocks($this->page) as $block) {
            $content .= BlockWidget::widget(['block' => $block]);
        }
        return $content;
    }
}
