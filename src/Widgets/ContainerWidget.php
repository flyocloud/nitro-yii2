<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\PagesInner;
use Flyo\Yii\Module;
use yii\base\InvalidConfigException;
use yii\base\Widget;

/**
 * Nav
 * 
 * ```php
 * <?php $container = ContainerWidget::begin(['identifier' => 'main']); ?>
 *       <ul>
 *       <?php foreach ($container->getItems() as $item): ?>
 *           <li><?= Html::a($item->getLabel(), $item->getHref()); ?></li>
 *       <?php endforeach; ?>
 *       </ul>
 *   <?php $container::end(); ?>
 * ```
 */
class ContainerWidget extends Widget
{
    public $identifier;

    public function init()
    {
        parent::init();

        if (empty($this->identifier)) {
            throw new InvalidConfigException("The identifier property can not be empty.");
        }

        ob_start();
    }

    public function getContainer()
    {
        return Module::getInstance()->getConfig()->getContainers()[$this->identifier];
    }

    /**
     * @return PagesInner[]
     */
    public function getItems() : array
    {
        return $this->getContainer()->getItems();
    }

    public function run()
    {
        return ob_get_clean();
    }
}