<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\PagesInner;
use Flyo\Yii\Module;
use yii\base\Widget;

class NavWidget extends Widget
{
    public function init()
    {
        parent::init();
        ob_start();
    }

    /**
     * @return PagesInner[]
     */
    public function getItems() : array
    {
        return Module::getInstance()->getConfig()->getNav()->getItems();
    }

    public function run()
    {
        return ob_get_clean();
    }
}