<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\Block;
use Yii;
use yii\base\Widget;
use yii\web\View;

/**
 * Open the block in flyo when clicking on it in the preview explorere.
 *
 * Example usage:
 *
 * ```
 * OpenBlockInFlyo::begin(['block' => $block]);
 *  ...
 * OpenBlockInFlyo::end();
 * ```
 */
class OpenBlockInFlyo extends Widget
{
    public Block $block;

    private $_isEnabled;

    public function setIsEnabled(bool $isEnabled)
    {
        $this->_isEnabled = $isEnabled;
    }

    public function getIsEnabled(): bool
    {
        return $this->_isEnabled === null ? !YII_ENV_PROD : $this->_isEnabled;
    }

    public function init()
    {
        parent::init();
        ob_start();
    }

    public function run()
    {
        $content = ob_get_clean();

        if (!$this->getIsEnabled()) {
            return $content;
        }

        /** @var View $view */
        $view = Yii::$app->view;
        $view->registerJs(
            <<<EOT
function getActualWindow() {
    if (window === window.top) {
        return window;
    } else if (window.parent) {
        return window.parent;
    }
    return window;
  }

function openBlockInFlyo(uid) {
    getActualWindow().postMessage({
        action: 'openEdit',
        data: JSON.parse(JSON.stringify({item: {uid: uid}}))
    },'https://flyo.cloud')
}
EOT,
            View::POS_END,
            'flyo-preview-klick'
        );
        return '<div onclick="openBlockInFlyo(\''.$this->block->getUid().'\')">'.$content.'</div>';
    }
}
