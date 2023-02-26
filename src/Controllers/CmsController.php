<?php

namespace Flyo\Yii\Controllers;

use Flyo\Api\PagesApi;
use Flyo\Yii\Module;
use Yii;
use yii\helpers\Url;
use yii\web\Controller;

/**
 * @property Module $module
 */
class CmsController extends Controller
{
    public function actionIndex()
    {
        $pathOrSlug = Yii::$app->request->pathInfo;

        if (empty($pathOrSlug)) {
            $page = (new PagesApi())->home();
        } else {
            $page = (new PagesApi())->page($pathOrSlug);
        }

        $this->view->title = $page->getMetaJson()->getTitle();

        $this->render('@app/views/cms', [
            'page' => $page,
        ]);
    }
}