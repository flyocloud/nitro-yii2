<?php

namespace Flyo\Traits;

use Yii;

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
}