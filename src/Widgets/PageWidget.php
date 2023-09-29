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
            $flyo = OpenBlockInFlyo::begin(['block' => $block]);
            // the output of the blockwidget will be catched by "ob_start()" in the OpenBlockInFlyo block
            echo BlockWidget::widget(['block' => $block]);
            $content .= $flyo->run();
        }
        return $content;
    }
}
