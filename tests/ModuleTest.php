<?php

namespace Flyo\Yii\Tests;

use Flyo\ApiException;
use Flyo\Configuration;
use Flyo\Yii\Module;

class ModuleTest extends BaseTestCase
{
    public function testHost()
    {

        $module = new Module('flyo', null, ['token' => 'foobar']);
        try {
            $module->bootstrap($this->app);
        } catch (ApiException $exception) {
        }

        $cfg = Configuration::getDefaultConfiguration();
        $this->assertSame('https://api.flyo.cloud/nitro/v1', $cfg->getHost());
    }
}
