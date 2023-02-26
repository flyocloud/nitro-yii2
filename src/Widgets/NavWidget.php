<?php

namespace Flyo\Yii\Widgets;

use Flyo\Yii\Module;
use yii\base\Widget;

class NavWidget extends Widget
{
    public function init()
    {
        parent::init();
        ob_start();
    }

    public function getItems() : array
    {
        return Module::getInstance()->getConfig()->getPages();
    }

    public function run()
    {
        return ob_get_clean();
    }
}