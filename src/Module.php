<?php

namespace Flyo\Yii;

use Exception;
use Flyo\Api\ConfigApi;
use Flyo\Configuration;
use Flyo\Model\Config200Response;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\base\Module as BaseModule;

/**
 * @property Config200Response $config
 */
class Module extends BaseModule implements BootstrapInterface
{
    public $controllerNamespace = 'Controllers';
    
    public $token;

    public function init()
    {
        parent::init();

        if (empty($this->token)) {
            throw new InvalidConfigException("The token param can not be empty for flyo nitro module.");
        }
    }

    private $_config;

    public function setConfig(Config200Response $config)
    {
        $this->_config = $config;
    }

    public function getConfig() : Config200Response
    {
        return $this->_config;
    }

    public function bootstrap($app)
    {
        $config = new Configuration();
        $config->setApiKey('token', $this->token);

        $this->setConfig((new ConfigApi())->config());

        $rules = [];
        foreach($this->config->getPages() as $page) {
            $rules["GET {$page}"] = "{$this->id}/cms/index";
        }

        $app->urlManager->addRules($rules);
    }
}