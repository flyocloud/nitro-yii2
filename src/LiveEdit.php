<?php

namespace Flyo\Yii;

use yii\web\View;

/**
 * Registers the nitro js bridge and boots the live edit integration for the current page.
 *
 * This is wired automatically by [[Module::bootstrap()]] for web applications whenever live edit is
 * enabled, therefore a project does not have to use [[\Flyo\Yii\Widgets\Editable]] in order to get
 * live edit (page refresh, scroll to block, editor handshake) working. Blocks which should be
 * clickable inside the flyo preview only have to render the `data-flyo-uid` attribute.
 */
class LiveEdit
{
    private const JS_KEY_BRIDGE = 'flyo-bridge-cdn';
    private const JS_KEY_BOOT   = 'flyo-live-edit-boot';

    /**
     * Register the bridge file and the (once only) live edit boot script.
     *
     * The url of the bridge is configured by [[Module::$liveEditBridgeUrl]].
     */
    public static function register(View $view, string $bridgeUrl = Module::LIVE_EDIT_BRIDGE_URL): void
    {
        if (isset($view->js[View::POS_END][self::JS_KEY_BOOT])) {
            return;
        }

        // the file is rendered before the inline scripts of the same position, therefore
        // `window.nitroJsBridge` is available when the boot script runs.
        $view->registerJsFile($bridgeUrl, ['position' => View::POS_END], self::JS_KEY_BRIDGE);
        $view->registerJs(self::bootJs(), View::POS_END, self::JS_KEY_BOOT);
    }

    /**
     * The boot script which is registered at the end of the body.
     */
    private static function bootJs(): string
    {
        return <<<JS
(function(){
  var bridge = window.nitroJsBridge;
  if (!bridge) {
    return;
  }

  // reload() is the live-edit boot call: it wires pageRefresh reloads and
  // (bridge >= 1.4.0) the editor connection handshake.
  if (typeof bridge.reload === 'function') {
    bridge.reload();
  }

  if (typeof bridge.scrollTo === 'function') {
    bridge.scrollTo();
  }

  if (typeof bridge.highlightAndClick !== 'function') {
    return;
  }

  var nodes = document.querySelectorAll('[data-flyo-uid]');
  for (var i = 0; i < nodes.length; i++) {
    var el = nodes[i];
    var uid = el.getAttribute('data-flyo-uid');
    if (uid) {
      bridge.highlightAndClick(uid, el);
    }
  }
})();
JS;
    }
}
