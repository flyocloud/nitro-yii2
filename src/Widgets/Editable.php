<?php

namespace Flyo\Yii\Widgets;

use Flyo\Yii\Accessor;
use Flyo\Yii\Module;
use yii\base\Widget;

/**
 * New name for the widget. Contains the implementation formerly in OpenBlockInFlyo.
 *
 * The widget only renders the `data-flyo-uid` marker which makes the block clickable inside the flyo
 * preview. The nitro js bridge itself is registered by [[\Flyo\Yii\Module::bootstrap()]] whenever live
 * edit is enabled, see [[\Flyo\Yii\LiveEdit]].
 */
class Editable extends Widget
{
    /**
     * @var object The block to mark, usually a `Flyo\Model\Block`. Any object which provides the uid of a
     * block is accepted, see [[Accessor::read()]], therefore the widget also works with your own block
     * models, see [[\Flyo\Yii\Module::$blockModels]].
     */
    public object $block;

    /**
     * @deprecated The widget has no own toggle anymore, configure [[\Flyo\Yii\Module::$liveEdit]] instead.
     */
    public function setIsEnabled(bool $isEnabled): void
    {
        @trigger_error(__METHOD__ . ' is deprecated and has no effect. Configure Flyo\\Yii\\Module::$liveEdit instead.', E_USER_DEPRECATED);
    }

    /**
     * @deprecated Use [[\Flyo\Yii\Module::getIsLiveEditEnabled()]] instead.
     */
    public function getIsEnabled(): bool
    {
        @trigger_error(__METHOD__ . ' is deprecated. Use Flyo\\Yii\\Module::getInstance()->getIsLiveEditEnabled() instead.', E_USER_DEPRECATED);
        return Module::getInstance()->getIsLiveEditEnabled();
    }

    public function init()
    {
        parent::init();
        ob_start();
    }

    public function run()
    {
        $content = ob_get_clean();

        if (!Module::getInstance()->getIsLiveEditEnabled()) {
            return $content;
        }

        $uid = Accessor::uid($this->block);

        // Keep your current behavior: we generate a wrapper <div> around the buffered content
        return '<div data-flyo-uid="' . htmlspecialchars($uid, ENT_QUOTES) . '">' . $content . '</div>';
    }

    /**
     * Static helper to use on an existing element (no extra wrapper).
     *
     * Example:
     *   <section <?= \Flyo\Yii\Widgets\Editable::attr($block) ?>>
     *       ...
     *   </section>
     */
    public static function attr(object $block): string
    {
        $uid = htmlspecialchars(Accessor::uid($block), ENT_QUOTES);
        return 'data-flyo-uid="' . $uid . '"';
    }
}
