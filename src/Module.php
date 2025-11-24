<?php

namespace Flyo\Yii;

use Flyo\Api\ConfigApi;
use Flyo\Api\VersionApi;
use Flyo\Configuration;
use Flyo\Model\ConfigResponse;
use Flyo\Model\Page;
use Flyo\Model\VersionResponse;
use Flyo\Yii\Cache\VersionCacheDependency;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\base\Module as BaseModule;
use yii\web\Application;
use yii\web\Response;
use yii\web\UrlRule;

/**
 * @property ConfigResponse $config
 * @property Page $currentPage
 */
class Module extends BaseModule implements BootstrapInterface
{
    /**
     * @var string The [[Flyo\Yii\Events\PageResolveEvent]] that is triggered when a page is resolved. Only successfull resolutions trigger this event.
     */
    public const EVENT_PAGE_RESOLVE = 'pageResolve';

    public $controllerNamespace = 'Flyo\Yii\Controllers';

    /**
     * @var string If defined, the configuration will use the given host instead of the default one. Ensure the host contains the version information like `localflyo.com/nitro/v1` without trailing slash.
     */
    public $host;

    /**
     * @var string The flyo api token from the flyo.cloud dashboard.
     */
    public $token;


    /**
     * @var boolean If enabled, and the application has configured a cache component, the page will be cached on the server side for [[$cacheDuration]] seconds.
     */
    public $serverPageCache = true;

    /**
     * @var int If enabled, and the application has configured a cache component, the page will be cached on the server side for this many seconds.
     */
    public $serverPageCacheDuration = 3600; // 1h

    /**
     * @var boolean Whether a CDN cache header should be sent for pages or not, if enabled in production the page will be cached for [[$cdnCacheDuration]] seconds in
     * the CDN edge cache. In order to disable CDN Caching for a specific action you can set `Module::getInstance()->cdnCache = false;` there.
     */
    public $cdnCache = true;

    /**
     * @var int Whether a CDN cache header should be sent for pages or not, if enabled in production the page will be cached for this many seconds in
     * the CDN edge cache. Current supported CDNs are Vercel and generic CDN-Cache-Control.
     */
    public $cdnCacheDuration = 1800; // 30min

    /**
     * @var boolean Whether a client cache header should be sent for pages or not, if enabled in production the page will be cached for 30mins in
     * the clients browser cache.
     */
    public $clientHttpCache = true;

    /**
     * @var int The duration in seconds for the client cache header, if enabled in production the page will be cached for this many seconds in
     * the clients browser cache.
     */
    public $clientHttpCacheDuration = 1800; // 30min

    /**
     * @var callable Additinal variation informations for the page, for example if you have a custom query param somewhere else:
     *
     * 'cacheVariation' => function() {
     *    return Yii::$app->request->getQueryParam('slug');
     * },
     *
     */
    public $cacheVariation;

    /**
     * @var array By default we only allow GET requests for all defined url rules, if you want to allow other request methods you can define them here.
     * Adding ['GET', 'POST'] can be useful for example if you want to use a form block inside a page.
     */
    public $urlRuleVerbs = ['GET'];

    public function init()
    {
        parent::init();

        if (empty($this->token)) {
            throw new InvalidConfigException("The token param can not be empty for flyo nitro module.");
        }
    }

    private $_config;

    public function setConfig(ConfigResponse $config)
    {
        $this->_config = $config;
    }

    public function getConfig(): ConfigResponse
    {
        return $this->_config;
    }

    private $_currentPage;

    public function setCurrentPage(Page $page)
    {
        $this->_currentPage = $page;
    }

    public function getCurrentPage(): ?Page
    {
        return $this->_currentPage;
    }

    private function getNitroConfig(): ConfigResponse
    {
        Yii::beginProfile('flyo-config', __METHOD__);
        $config = (new ConfigApi(null, Configuration::getDefaultConfiguration()))->config();
        Yii::endProfile('flyo-config', __METHOD__);
        return $config;
    }

    private static $versionApi;

    /**
     * @return VersionResponse
     */
    public static function getVersionApi(): VersionResponse
    {
        if (self::$versionApi === null) {
            Yii::beginProfile('flyo-version', __METHOD__);
            $versionApi = (new VersionApi(null, Configuration::getDefaultConfiguration()))->version();
            Yii::endProfile('flyo-version', __METHOD__);

            Yii::debug([
                'version' => $versionApi->getVersion(),
                'last_updated_at' => date("d.m.Y H:i", $versionApi->getUpdatedAt()),
            ], __METHOD__);

            self::$versionApi = $versionApi;
        }

        return self::$versionApi;
    }

    public function bootstrap($app)
    {
        /** @var Application $app */
        $config = new Configuration();
        $config->setApiKey('token', $this->token);

        if ($this->host) {
            $config->setHost($this->host);
        }

        Configuration::setDefaultConfiguration($config);

        $configApi = YII_ENV_PROD && $this->serverPageCache ? Yii::$app->cache->getOrSet(['flyo', 'config'], fn () => $this->getNitroConfig(), $this->serverPageCacheDuration, new VersionCacheDependency()) : $this->getNitroConfig();

        $this->setConfig($configApi);

        $rules = [];
        foreach ($this->config->getPages() as $page) {
            $rules[] = new UrlRule(['verb' => $this->urlRuleVerbs, 'pattern' => '<path:('.$page.')>', 'route' => "{$this->id}/nitro/index"]);
        }

        // To ensure proper prioritization, it is essential to prepend the rules. Otherwise, entity rules might take precedence over pages.
        $app->urlManager->addRules($rules, false);

        if (YII_ENV_PROD && Module::getInstance()->cdnCache) {
            $app->response->on(Response::EVENT_BEFORE_SEND, function (Event $event) {
                // its possible that during the runtime the cdnCache is disabled for specific actions
                // therefore we need to check it again here
                if (Module::getInstance()->cdnCache) {
                    /** @var Response $sender */
                    $sender = $event->sender;
                    $sender->headers->set('Vercel-CDN-Cache-Control', "max-age={$this->cdnCacheDuration}");
                    $sender->headers->set('CDN-Cache-Control', "max-age={$this->cdnCacheDuration}");
                }
            });
        }
    }
}
