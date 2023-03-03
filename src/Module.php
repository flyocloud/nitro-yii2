<?php

namespace Flyo\Yii;

use Exception;
use Flyo\Api\ConfigApi;
use Flyo\Configuration;
use Flyo\Model\ConfigResponse;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\base\Module as BaseModule;

/**
 * @property ConfigResponse $config
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

    public function bootstrap($app)
    {
        $config = new Configuration();
        $config->setApiKey('token', $this->token);

        Configuration::setDefaultConfiguration($config);

        Yii::beginProfile('flyo-config', __METHOD__);
        $this->setConfig((new ConfigApi(null, Configuration::getDefaultConfiguration()))->config());
        Yii::endProfile('flyo-config', __METHOD__);
        
        $rules = [];
        foreach($this->config->getPages() as $page) {
            Yii::debug('register page route: ' . $page, __METHOD__);
            $rules["GET {$page}"] = "{$this->id}/cms/index";
        }

        $app->urlManager->addRules($rules);
    }
}