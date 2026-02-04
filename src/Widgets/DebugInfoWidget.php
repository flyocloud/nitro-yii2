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
        $version = YII_ENV_PROD ? Module::getInstance()->getConfig()->getNitro()->getVersion() : '-';
        $lastUpdate = YII_ENV_PROD ? date("d.m.Y H:i", Module::getInstance()->getConfig()->getNitro()->getUpdatedAt()) : '-';
        $token = Module::getInstance()->token;
        $tokenType = str_starts_with($token, 'p-') ? 'production' : (str_starts_with($token, 'd-') ? 'develop' : 'unknown');
        $vercelDeploymentId = getenv('VERCEL_DEPLOYMENT_ID') ?: '-';
        $vercelGitCommitSha = getenv('VERCEL_GIT_COMMIT_SHA') ?: '-';
        return "<!-- " . implode(' | ', [
            "debug:{$debug}",
            "env:{$env}",
            "release:{$release}",
            "version:{$version}",
            "versiondate:{$lastUpdate}",
            "tokentype:{$tokenType}",
            "did:{$vercelDeploymentId}",
            "csha:{$vercelGitCommitSha}"
        ]) . " -->";
    }
}
