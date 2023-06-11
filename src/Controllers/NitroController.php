<?php

namespace Flyo\Yii\Controllers;

use Flyo\Api\PagesApi;
use Flyo\Configuration;
use Flyo\Yii\Module;
use Yii;
use yii\filters\PageCache;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * @property Module $module
 */
class NitroController extends Controller
{
    public function behaviors()
    {
        return [
            [
                'class' => PageCache::class,
                'enabled' => YII_ENV_PROD,
                'duration' => 0,
                'variations' => [
                    Module::getInstance()->getConfig()->getNitro()->getVersion(),
                ],
            ]
        ];
    }
    
    public function actionIndex()
    {
        $pathOrSlug = Yii::$app->request->pathInfo;

        Yii::debug('flyo resolve route: ' . $pathOrSlug, __METHOD__);

        Yii::beginProfile('flyo-page-'.$pathOrSlug, __METHOD__);
        if (empty($pathOrSlug)) {
            $page = (new PagesApi(null, Configuration::getDefaultConfiguration()))->home();
        } else {
            $page = (new PagesApi(null, Configuration::getDefaultConfiguration()))->page($pathOrSlug);
        }
        Yii::endProfile('flyo-page-'.$pathOrSlug, __METHOD__);

        if (!$page) {
            throw new NotFoundHttpException("Unable to find the given route");
        }

        $this->view->title = $page->getMetaJson()->getTitle();
        $this->view->registerMetaTag([
            'property' => 'og:title',
            'content' => $page->getMetaJson()->getTitle()
        ]);

        $this->view->registerMetaTag([
            'name' => 'description',
            'content' => $page->getMetaJson()->getDescription(),
        ]);

        $this->view->registerMetaTag([
            'property' => 'og:description',
            'content' => $page->getMetaJson()->getDescription()
        ]);
        
        $this->view->registerMetaTag([
            'property' => 'og:image',
            'content' => $page->getMetaJson()->getImage()
        ]);

        return $this->render('@app/views/nitro', [
            'page' => $page,
        ]);
    }
}