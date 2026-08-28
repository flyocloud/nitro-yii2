<?php

namespace Flyo\Yii\Tests;

use Flyo\Yii\Filters\NitroPageCache;
use Flyo\Yii\Module;

class NitroPageCacheTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        Module::setInstance(null);

        parent::tearDown();
    }

    private function createModule(): Module
    {
        $module = new Module('flyo', null, ['token' => 'foobar']);
        Module::setInstance($module);

        return $module;
    }

    public function testResponseIsCachedWhileTheCacheIsEnabled()
    {
        $this->createModule();

        $this->assertTrue((new NitroPageCache())->beforeCacheResponse());
    }

    public function testResponseIsNotCachedWhenTheCacheHasBeenDisabled()
    {
        $this->createModule()->disableCache();

        $this->assertFalse((new NitroPageCache())->beforeCacheResponse());
    }

    public function testResponseIsCachedWithoutAModuleInstance()
    {
        $this->assertTrue((new NitroPageCache())->beforeCacheResponse());
    }
}
