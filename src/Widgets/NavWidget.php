<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\PagesInner;
use Flyo\Yii\Module;
use yii\base\Widget;

/**
 * Nav
 * 
 * ```php
 * <?php $nav = NavWidget::begin(); ?>
 *       <ul>
 *       <?php foreach ($nav->getItems() as $item): ?>
 *           <li><?= Html::a($item->getLabel(), $item->getPath()); ?></li>
 *       <?php endforeach; ?>
 *       </ul>
 *   <?php $nav::end(); ?>
 * ```
 */
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