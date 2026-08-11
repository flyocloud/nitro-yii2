<?php

namespace Flyo\Yii\Actions;

use Flyo\Api\SitemapApi;
use Flyo\Configuration;
use Flyo\Model\EntityinterfaceInner;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\web\Response;

class SitemapAction extends Action
{
    public $domain;

    /**
     * @deprecated The url of a sitemap entry is built from the `href` value of the api response, therefore the
     * route name has no effect anymore.
     */
    public function setDetailRouteName(string $detailRouteName): void
    {
        @trigger_error(__METHOD__ . ' is deprecated and has no effect. The sitemap uses the href value delivered by the api.', E_USER_DEPRECATED);
    }

    public function init()
    {
        parent::init();

        if (!$this->domain) {
            throw new InvalidConfigException('The property "$domain" can not be empty. Example "https://example.com"');
        }
    }

    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/xml');

        return $this->generateXml((new SitemapApi(null, Configuration::getDefaultConfiguration()))->sitemap());
    }

    /**
     * Generate the sitemap xml for the given items of the sitemap api response.
     *
     * @param EntityinterfaceInner[] $items The sitemap items.
     * @return string The sitemap xml.
     */
    public function generateXml(iterable $items)
    {
        $urls = [];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($items as $item) {
            $href = $item->getHref();

            // an item without a resolved href has no url to point to (f.e. an entity which is not mapped
            // to any route), the same href can be delivered by multiple containers.
            if (!$href || in_array($href, $urls, true)) {
                continue;
            }

            $urls[] = $href;

            $xml .= '<url><loc>'.$this->buildUrl($href).'</loc>';

            if ($lastmod = $this->buildLastmod($item)) {
                $xml .= '<lastmod>'.$lastmod.'</lastmod>';
            }

            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Generate the W3C datetime for the `lastmod` value of a given sitemap item.
     *
     * @param EntityinterfaceInner $item The sitemap item from the api response.
     * @return string|null The `lastmod` value or null if the item does not provide an update time.
     */
    private function buildLastmod(EntityinterfaceInner $item)
    {
        $updatedAt = (int) $item->getUpdatedAt();

        return $updatedAt > 0 ? gmdate(DATE_W3C, $updatedAt) : null;
    }

    private function buildUrl($path)
    {
        return rtrim($this->domain, '/') . '/' . ltrim($path, '/');
    }
}
