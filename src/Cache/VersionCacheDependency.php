<?php

namespace Flyo\Yii\Cache;

use Flyo\Api\VersionApi;
use Flyo\Configuration;
use Yii;
use yii\caching\Dependency;

class VersionCacheDependency extends Dependency
{
    public $reusable = true;

    protected function generateDependencyData($cache)
    {
        Yii::beginProfile('flyo-version', __METHOD__);
        $versionApi = (new VersionApi(null, Configuration::getDefaultConfiguration()))->version();
        Yii::endProfile('flyo-version', __METHOD__);
        
        Yii::debug(['version' => $versionApi->getVersion(), 'last_updated_at' => date("d.m.Y H:i", $versionApi->getUpdatedAt())], __METHOD__);
        return $versionApi->getVersion();
    }
}