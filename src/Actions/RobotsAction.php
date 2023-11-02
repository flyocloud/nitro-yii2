<?php

namespace Flyo\Yii\Actions;

use Yii;
use yii\base\Action;
use yii\web\Response;

class RobotsAction extends Action
{
    public $sitemap;

    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/plain');

        if (YII_ENV_PROD) {
            $response = 'User-agent: *' . PHP_EOL . 'Allow: /';

            if ($this->sitemap) {
                $response .= PHP_EOL . 'Sitemap: ' . $this->sitemap;
            }

            return $response;
        }

        return 'User-agent: *' . PHP_EOL . 'Disallow: /';
    }
}
