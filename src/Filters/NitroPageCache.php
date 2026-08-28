<?php

namespace Flyo\Yii\Filters;

use Flyo\Yii\Module;
use yii\filters\PageCache;

/**
 * A page cache which respects [[Module::disableCache()]].
 *
 * The regular [[PageCache]] decides in `beforeAction()` whether it records the output, at that point the action has
 * not run yet and therefore it is not known if the response may be cached at all. A draft entity for example is only
 * resolved while the action runs, so this filter checks again right before the recorded output is written into the
 * cache and drops it when the caching has been turned off in the meantime.
 *
 * Use it wherever [[\Flyo\Yii\Actions\EntityAction]] is served, otherwise a draft would be stored on the server and
 * delivered to everybody requesting the same url:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => \Flyo\Yii\Filters\NitroPageCache::class,
 *             'only' => ['detail'],
 *             'enabled' => YII_ENV_PROD && Module::getInstance()->serverPageCache,
 *             'duration' => Module::getInstance()->serverPageCacheDuration,
 *             'dependency' => new \Flyo\Yii\Cache\VersionCacheDependency(),
 *             'variations' => [Yii::$app->request->getQueryParam('slug')],
 *         ],
 *     ];
 * }
 * ```
 */
class NitroPageCache extends PageCache
{
    /**
     * @return bool|array
     */
    public function beforeCacheResponse()
    {
        $module = Module::getInstance();

        if ($module && $module->getIsCacheDisabled()) {
            return false;
        }

        return parent::beforeCacheResponse();
    }
}
