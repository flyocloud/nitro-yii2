<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\Page;
use yii\base\Widget;

class PageWidget extends Widget
{
    public Page $page;

    public function run()
    {
        $content = '';
        foreach ($this->page->getJson() as $block) {
            $content .= BlockWidget::widget(['block' => $block]);
        }
        return $content;
    }
}