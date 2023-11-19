<?php

namespace Flyo\Yii\Traits;

use Flyo\Model\Entity;
use Yii;
use yii\helpers\Json;
use yii\web\View;

trait MetaDataTrait
{
    public function registerData($title, $description, $imageSource)
    {
        /** @var View $view */
        $view = Yii::$app->view;

        $view->title = $title;
        $view->registerMetaTag(['name' => 'description', 'content' => $description]);

        $view->registerMetaTag(['property' => 'og:type', 'content' => 'website']);
        $view->registerMetaTag(['property' => 'og:title', 'content' => $title]);
        $view->registerMetaTag(['property' => 'og:description', 'content' => $description]);
        $view->registerMetaTag(['property' => 'og:image', 'content' => $imageSource]);

        $view->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary_large_image']);
        $view->registerMetaTag(['name' => 'twitter:title', 'content' => $title]);
        $view->registerMetaTag(['name' => 'twitter:description', 'content' => $description]);
        $view->registerMetaTag(['name' => 'twitter:image', 'content' => $imageSource]);
    }

    public function registerEntity(Entity $entity)
    {
        $this->registerData($entity->getEntity()->getEntityTitle(), $entity->getEntity()->getEntityTeaser(), $entity->getEntity()->getEntityImage());

        Yii::$app->view->on(View::EVENT_BEGIN_BODY, function () use ($entity) {
            echo '<script type="application/ld+json">' . Json::encode($entity->getJsonld()) . '</script>';
        });
    }

    public function registerMetricPixel(Entity $entity)
    {
        if (YII_ENV_PROD && !YII_DEBUG) {
            /** @var View $view */
            $view = Yii::$app->view;
            $view->registerJs("fetch('{$entity->getEntity()->getEntityMetric()->getApi()}')", View::POS_END);
        }
    }
}
