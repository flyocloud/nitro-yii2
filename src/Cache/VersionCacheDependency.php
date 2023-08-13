<?php

namespace Flyo\Yii\Cache;

use Flyo\Api\CacheApi;
use Flyo\Configuration;
use Yii;
use yii\caching\Dependency;

class VersionCacheDependency extends Dependency
{
    public $reusable = true;

    protected function generateDependencyData($cache)
    {
        Yii::beginProfile('flyo-version', __METHOD__);
        $cacheApi = (new CacheApi(null, Configuration::getDefaultConfiguration()))->cache();
        Yii::endProfile('flyo-version', __METHOD__);
        
        Yii::debug(['version' => $cacheApi->getVersion(), 'last_updated_at' => date("d.m.Y H:i", $cacheApi->getUpdatedAt())], __METHOD__);
        return $cacheApi->getVersion();
    }
}