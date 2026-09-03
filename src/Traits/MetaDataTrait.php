<?php

namespace Flyo\Yii\Traits;

use Flyo\Bridge\Image;
use Flyo\Model\Entity;
use Flyo\Model\Page;
use Yii;
use yii\helpers\Json;
use yii\helpers\Url;
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
        if (!empty($imageSource)) {
            $view->registerMetaTag(['property' => 'og:image', 'content' => Image::source($imageSource, 1200, 630, 'jpg')]);
        }

        $view->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary_large_image']);
        $view->registerMetaTag(['name' => 'twitter:title', 'content' => $title]);
        $view->registerMetaTag(['name' => 'twitter:description', 'content' => $description]);
        if (!empty($imageSource)) {
            $view->registerMetaTag(['name' => 'twitter:image', 'content' => Image::source($imageSource, 1200, 600, 'jpg')]);
        }
    }

    public function registerCanonical($url)
    {
        /** @var View $view */
        $view = Yii::$app->view;

        $url = rtrim(Url::base(true), '/') . '/' . ltrim($url, '/');

        $view->registerLinkTag(['rel' => 'canonical', 'href' => $url]);
    }

    /**
     * Register the robots meta tag for a page or entity which must not be indexed by search engines.
     *
     * This is not access control, the page is still delivered and reachable by its url.
     */
    public function registerNoIndex()
    {
        /** @var View $view */
        $view = Yii::$app->view;

        $view->registerMetaTag(['name' => 'robots', 'content' => 'noindex, nofollow'], 'robots');
    }

    /**
     * Register the robots meta tag whenever the given `is_indexable` information is explicitly falsy.
     *
     * A `null` value means the information is not provided (older Nitro API), the page is then treated as indexable.
     *
     * @param bool|int|null $isIndexable
     */
    public function registerRobots($isIndexable)
    {
        if ($isIndexable !== null && !$isIndexable) {
            $this->registerNoIndex();
        }
    }

    public function registerPage(Page $page)
    {
        $this->registerData($page->getMetaJson()->getTitle(), $page->getMetaJson()->getDescription(), $page->getMetaJson()->getImage());
        $this->registerJsonld($page->getJsonld());
        $this->registerRobots($page->getIsIndexable());

        if (!empty($page->getHref())) {
            $this->registerCanonical($page->getHref());
        }
    }

    public function registerEntity(Entity $entity)
    {
        $this->registerData($entity->getEntity()->getEntityTitle(), $entity->getEntity()->getEntityTeaser(), $entity->getEntity()->getEntityImage());
        $this->registerJsonld($entity->getJsonld());
        $this->registerRobots($entity->getIsIndexable());

        if (!empty($entity->getCanonical())) {
            $this->registerCanonical($entity->getCanonical());
        }
    }

    /**
     * Register a JSON-LD object (as provided by the Nitro API for pages and entities) in the body of the current view.
     *
     * @param object|array|null $jsonld
     */
    public function registerJsonld($jsonld)
    {
        $script = $this->generateJsonldScript($jsonld);

        if ($script === '') {
            return;
        }

        Yii::$app->view->on(View::EVENT_BEGIN_BODY, function () use ($script) {
            echo $script;
        });
    }

    /**
     * Generate the script tag for a given JSON-LD object, an empty string is returned when there is no data to render.
     *
     * @param object|array|null $jsonld
     */
    public function generateJsonldScript($jsonld): string
    {
        $data = is_object($jsonld) ? get_object_vars($jsonld) : $jsonld;

        if (empty($data)) {
            return '';
        }

        // htmlEncode escapes <, > and & as unicode sequences, therefore the json can not break out of the script tag.
        return '<script type="application/ld+json">' . Json::htmlEncode($jsonld) . '</script>';
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
