<?php

namespace Flyo\Yii\Controllers;

use Flyo\Yii\Actions\PageAction;
use Flyo\Yii\Cache\VersionCacheDependency;
use Flyo\Yii\Module;
use Flyo\Yii\Traits\MetaDataTrait;
use Yii;
use yii\filters\HttpCache;
use yii\filters\PageCache;
use yii\web\Controller;

/**
 * @property Module $module
 */
class NitroController extends Controller
{
    use MetaDataTrait;

    public function behaviors()
    {
        return [
            [
                'class' => PageCache::class,
                'enabled' => YII_ENV_PROD && Module::getInstance()->serverPageCache,
                'duration' => Module::getInstance()->serverPageCacheDuration,
                'dependency' => new VersionCacheDependency(),
                'variations' => $this->getCacheVariation(),
            ],
            [
                'class' => HttpCache::class,
                'enabled' => YII_ENV_PROD && Module::getInstance()->clientHttpCache,
                'cacheControlHeader' => 'public, max-age=1800', // 30min client caching
                'lastModified' => function () {
                    return Module::getVersionApi()->getUpdatedAt();
                },
            ]
        ];
    }

    private function getCacheVariation()
    {
        $callable = Module::getInstance()->cacheVariation;

        $variation = null;
        if ($callable && is_callable($callable)) {
            $variation = call_user_func($callable);
        }

        return array_filter([$variation, Yii::$app->request->getQueryParam('path')]);
    }

    public function actions()
    {
        return [
            'index' => [
                'class' => PageAction::class,
            ]
        ];
    }
}
