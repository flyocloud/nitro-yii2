<?php

namespace Flyo\Yii\Actions;

use Flyo\Api\PagesApi;
use Flyo\Configuration;
use Flyo\Yii\Events\OnPageResolveEvent;
use Flyo\Yii\Module;
use Flyo\Yii\Traits\MetaDataTrait;
use Yii;
use yii\base\Action;
use yii\web\NotFoundHttpException;

class PageAction extends Action
{
    use MetaDataTrait;

    public function run($path = null)
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

        $event = new OnPageResolveEvent();
        $event->page = $page;
        $this->trigger(Module::EVENT_ON_PAGE_RESOLVE, $event);

        Module::getInstance()->setCurrentPage($page);

        $this->registerData($page->getMetaJson()->getTitle(), $page->getMetaJson()->getDescription(), $page->getMetaJson()->getImage());

        return $this->controller->render('@app/views/nitro', [
            'page' => $page,
        ]);
    }
}
