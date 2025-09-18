<?php

namespace Flyo\Yii\Widgets;

use Flyo\Model\Block;
use Yii;
use yii\base\Widget;
use yii\web\View;

class OpenBlockInFlyo extends Widget
{
    public Block $block;

    private ?bool $_isEnabled = null;

    private const CDN_URL              = 'https://unpkg.com/@flyo/nitro-js-bridge@1/dist/nitro-js-bridge.umd.cjs';
    private const JS_KEY_CDN           = 'flyo-bridge-cdn';
    private const JS_KEY_HIGHLIGHTER   = 'flyo-preview-highlight-loader';

    /**
     * Optional toggle (default: enabled when not YII_ENV_PROD)
     */
    public function setIsEnabled(bool $isEnabled): void
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

        self::ensureAssets();

        $uid = $this->block->getUid();

        // Keep your current behavior: we generate a wrapper <div> around the buffered content
        return '<div data-flyo-uid="' . htmlspecialchars($uid, ENT_QUOTES) . '">' . $content . '</div>';
    }

    /**
     * Static helper to use on an existing element (no extra wrapper).
     *
     * Example:
     *   <section <?= \Flyo\Yii\Widgets\OpenBlockInFlyo::attr($block) ?>>
     *       ...
     *   </section>
     */
    public static function attr(Block $block): string
    {
        // Respect the same default enablement as the widget
        $enabled = !YII_ENV_PROD;
        if ($enabled) {
            self::ensureAssets();
        }

        $uid = htmlspecialchars($block->getUid(), ENT_QUOTES);
        return 'data-flyo-uid="' . $uid . '"';
    }

    /**
     * Register the bridge and the (once-only) highlighter that scans [data-flyo-uid].
     */
    protected static function ensureAssets(): void
    {
        /** @var View $view */
        $view = Yii::$app->view;

        // Include the UMD build once
        $view->registerJsFile(self::CDN_URL, ['position' => View::POS_END], self::JS_KEY_CDN);

        // Register the minimal highlighter once (no classes, only [data-flyo-uid])
        if (!isset($view->js[View::POS_END][self::JS_KEY_HIGHLIGHTER])) {
            $view->registerJs(<<<JS
(function(){
  var bridge = window.nitroJsBridge;
  if (!bridge || typeof bridge.highlightAndClick !== 'function') return;

  var nodes = document.querySelectorAll('[data-flyo-uid]');
  for (var i = 0; i < nodes.length; i++) {
    var el = nodes[i];
    var uid = el.getAttribute('data-flyo-uid');
    if (uid) {
      bridge.highlightAndClick(uid, el);
    }
  }
})();
JS, View::POS_END, self::JS_KEY_HIGHLIGHTER);
        }
    }
}
