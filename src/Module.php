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
     * @var integer The number of seconds the page keeps in the cache, if you use 0 the cache will never be cleared. We use a high value, but not 0 (forever) because if you
     * use memcache with a persistante storage this can lead to costs using services like upstash.com therefore we use 2 weeks of cache duration:
     * 60 * 60 * 24 * 14 = 1209600
     * 60 * 15 = 900 (15min)
     */
    public $cacheDuration = 1209600;

    /**
     * @var boolean If enabled, and the application has configured a cache component, the page will be cached on the server side for [[$cacheDuration]] seconds.
     */
    public $serverPageCache = true;

    /**
     * @var boolean Whether a client cache header should be sent for pages or not, if enabled in production the page will be cached for 30mins in
     * the clients browser cache.
     */
    public $clientHttpCache = true;

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

        $configApi = YII_ENV_PROD ? Yii::$app->cache->getOrSet(['flyo', 'config'], fn () => $this->getNitroConfig(), $this->cacheDuration, new VersionCacheDependency()) : $this->getNitroConfig();

        $this->setConfig($configApi);

        $rules = [];
        foreach ($this->config->getPages() as $page) {
            $rules[] = new UrlRule(['verb' => $this->urlRuleVerbs, 'pattern' => '<path:('.$page.')>', 'route' => "{$this->id}/nitro/index"]);
        }

        // To ensure proper prioritization, it is essential to prepend the rules. Otherwise, entity rules might take precedence over pages.
        $app->urlManager->addRules($rules, false);

        if (YII_ENV_PROD && Module::getInstance()->serverPageCache) {
            $app->response->on(Response::EVENT_BEFORE_SEND, function (Event $event) {
                /** @var Response $sender */
                $sender = $event->sender;
                $sender->headers->set('Vercel-CDN-Cache-Control', "max-age={$this->cacheDuration}");
                $sender->headers->set('CDN-Cache-Control', "max-age={$this->cacheDuration}");
            });
        }
    }
}
