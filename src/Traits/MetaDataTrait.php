<?php

namespace Flyo\Yii\Traits;

use Flyo\Model\Entity;
use Yii;
use yii\web\View;

trait MetaDataTrait
{
    public function registerData($title, $description, $imageSource)
    {
        $view = Yii::$app->view;

        $view->title = $title;
        $view->registerMetaTag([
            'property' => 'og:title',
            'content' => $title
        ]);

        $view->registerMetaTag([
            'name' => 'description',
            'content' => $description,
        ]);

        $view->registerMetaTag([
            'property' => 'og:description',
            'content' => $description
        ]);
        
        $view->registerMetaTag([
            'property' => 'og:image',
            'content' => $imageSource,
        ]);
    }

    public function registerEntity(Entity $entity)
    {
        $this->registerData($entity->getEntity()->getEntityTitle(), $entity->getEntity()->getEntityTeaser(), $entity->getEntity()->getEntityImage());
    }

    public function registerMetricPixel(Entity $entity)
    {
        if (!YII_DEBUG) {
            Yii::$app->view->registerJs("fetch('{$entity->getEntity()->getEntityMetric()->getApi()}')", View::POS_END);
        }
    }
}