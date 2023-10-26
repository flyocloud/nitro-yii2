<?php

namespace Flyo\Yii\Widgets;

use Flyo\Yii\Module;
use Yii;
use yii\base\Widget;

class DebugInfoWidget extends Widget
{
    public function run()
    {
        $debug = var_export(YII_DEBUG, true);
        $env = YII_ENV;
        $release = Yii::$app->version;
        $version = YII_ENV_PROD ? Module::getVersionApi()->getVersion() : '-';
        $lastUpdate = YII_ENV_PROD ? date("d.m.Y H:i", Module::getVersionApi()->getUpdatedAt()) : '-';

        return "<!-- " . implode(' | ', ["debug:{$debug}", "env:{$env}", "release:{$release}", "version:{$version}", "version date:{$lastUpdate}"]) . " -->";
    }
}
