<?php

namespace Flyo\Yii\Actions;

use Flyo\Api\SitemapApi;
use Flyo\Configuration;
use Yii;
use yii\base\Action;
use yii\web\Response;

class SitemapAction extends Action
{
    public $detailRouteName = 'detail';

    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/xml');

        $routes = [];
        $api = (new SitemapApi(null, Configuration::getDefaultConfiguration()))->sitemap();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($api as $item) {

            if ($item->getEntityType() == 'nitro-page') {
                if (in_array($item->getEntitySlug(), $routes)) {
                    continue;
                }
                $routes[] = $item->getEntitySlug();
                $xml .= '<url><loc>/'.$item->getEntitySlug().'</loc></url>';
            } elseif (isset($item->getRoutes()['detail'])) {
                $xml .= '<url><loc>'.$item->getRoutes()[$this->detailRouteName].'</loc></url>';
            }
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
