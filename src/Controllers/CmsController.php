<?php

namespace Flyo\Yii\Controllers;

use Flyo\Api\PagesApi;
use Flyo\Configuration;
use Flyo\Yii\Module;
use Yii;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * @property Module $module
 */
class CmsController extends Controller
{
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

        return $this->render('@app/views/cms', [
            'page' => $page,
        ]);
    }
}