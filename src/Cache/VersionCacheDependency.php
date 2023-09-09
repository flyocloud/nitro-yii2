<?php

namespace Flyo\Yii\Cache;

use Flyo\Yii\Module;
use yii\caching\Dependency;

class VersionCacheDependency extends Dependency
{
    public $reusable = true;

    protected function generateDependencyData($cache)
    {
        return Module::getVersionApi()->getVersion();
    }
}
