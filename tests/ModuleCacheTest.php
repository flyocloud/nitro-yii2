<?php

namespace Flyo\Yii\Tests;

use Flyo\Yii\Module;
use yii\web\Response;

class ModuleCacheTest extends BaseTestCase
{
    private function createModule(array $config = []): Module
    {
        return new Module('flyo', null, array_merge(['token' => 'foobar'], $config));
    }

    public function testCacheIsEnabledByDefault()
    {
        $module = $this->createModule();

        $this->assertFalse($module->getIsCacheDisabled());
        $this->assertTrue($module->serverPageCache);
        $this->assertTrue($module->cdnCache);
        $this->assertTrue($module->clientHttpCache);
    }

    public function testDisableCacheTurnsOffEveryLayer()
    {
        $module = $this->createModule();
        $module->disableCache();

        $this->assertTrue($module->getIsCacheDisabled());
        $this->assertFalse($module->serverPageCache);
        $this->assertFalse($module->cdnCache);
        $this->assertFalse($module->clientHttpCache);
    }

    public function testDisabledCacheSendsNoStoreHeaders()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 'public, max-age=1800');
        $response->headers->set('Last-Modified', 'Wed, 21 Oct 2026 07:28:00 GMT');
        $response->headers->set('Etag', '"abc"');

        $module = $this->createModule();
        $module->disableCache();
        $module->applyResponseCacheHeaders($response);

        $this->assertSame('no-store, no-cache, must-revalidate, max-age=0', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
        $this->assertSame('no-store', $response->headers->get('CDN-Cache-Control'));
        $this->assertSame('no-store', $response->headers->get('Vercel-CDN-Cache-Control'));
        $this->assertNull($response->headers->get('Last-Modified'));
        $this->assertNull($response->headers->get('Etag'));
    }

    public function testEnabledCacheKeepsTheClientCacheHeaderUntouched()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 'public, max-age=1800');

        $this->createModule()->applyResponseCacheHeaders($response);

        $this->assertSame('public, max-age=1800', $response->headers->get('Cache-Control'));
    }
}
