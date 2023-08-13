<?php

namespace Flyo\Yii;

use Exception;
use Flyo\Api\ConfigApi;
use Flyo\Configuration;
use Flyo\Model\ConfigResponse;
use Flyo\Model\Page;
use Flyo\Yii\Cache\VersionCacheDependency;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\base\Module as BaseModule;
use yii\web\UrlRule;

/**
 * @property ConfigResponse $config
 * @property Page $currentPage
 */
class Module extends BaseModule implements BootstrapInterface
{
    public $controllerNamespace = 'Flyo\Yii\Controllers';
    
    public $token;

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

    public function getConfig() : ConfigResponse
    {
        return $this->_config;
    }

    private $_currentPage;

    public function setCurrentPage(Page $page)
    {
        $this->_currentPage = $page;
    }

    public function getCurrentPage()
    {
        return $this->_currentPage;
    }

    private function getNitroConfig()
    {
        Yii::beginProfile('flyo-config', __METHOD__);
        $config = (new ConfigApi(null, Configuration::getDefaultConfiguration()))->config();
        Yii::endProfile('flyo-config', __METHOD__);
        return $config;
    }

    public function bootstrap($app)
    {
        $config = new Configuration();
        $config->setApiKey('token', $this->token);

        Configuration::setDefaultConfiguration($config);

        
        $configApi = YII_ENV_PROD ? Yii::$app->cache->getOrSet(['flyo', 'config'], fn() => $this->getNitroConfig(), 0, new VersionCacheDependency()) : $this->getNitroConfig();

        $this->setConfig($configApi);
        
        $rules = [];
        foreach($this->config->getPages() as $page) {
            $rules[] = new UrlRule(['verb' => 'GET', 'pattern' => '<path:('.$page.')>', 'route' => "{$this->id}/nitro/index"]);
        }

        // To ensure proper prioritization, it is essential to prepend the rules. Otherwise, entity rules might take precedence over pages.
        $app->urlManager->addRules($rules, false);
    }
}
