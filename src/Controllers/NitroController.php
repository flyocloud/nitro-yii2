<?php

namespace Flyo\Yii\Controllers;

use Flyo\Api\PagesApi;
use Flyo\Configuration;
use Flyo\Yii\Cache\VersionCacheDependency;
use Flyo\Yii\Module;
use Flyo\Yii\Traits\MetaDataTrait;
use Yii;
use yii\filters\HttpCache;
use yii\filters\PageCache;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

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
                'enabled' => YII_ENV_PROD,
                'duration' => Module::getInstance()->cacheDuration,
                'dependency' => new VersionCacheDependency(),
                'variations' => [
                    Yii::$app->request->getQueryParam('path')
                ]
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

    public function actionIndex($path = null)
    {
        $pathOrSlug = $path;

        Yii::debug('flyo resolve route: ' . $pathOrSlug, __METHOD__);

        Yii::beginProfile('flyo-page-'.$pathOrSlug, __METHOD__);
        if (empty($pathOrSlug)) {
            $page = (new PagesApi(null, Configuration::getDefaultConfiguration()))->home();
        } else {
            $page = (new PagesApi(null, Configuration::getDefaultConfiguration()))->page($pathOrSlug);
        }
        Yii::endProfile('flyo-page-'.$pathOrSlug, __METHOD__);

        if (!$page) {
            throw new NotFoundHttpException(sprintf("Not page with the slug %s exists.", $path));
        }

        Module::getInstance()->setCurrentPage($page);

        $this->registerData($page->getMetaJson()->getTitle(), $page->getMetaJson()->getDescription(), $page->getMetaJson()->getImage());

        return $this->render('@app/views/nitro', [
            'page' => $page,
        ]);
    }
}
