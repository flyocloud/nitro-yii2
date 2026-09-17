<?php

namespace Flyo\Yii\Tests;

use Flyo\Yii\Cache\VersionCacheDependency;
use Flyo\Yii\Module;
use yii\caching\ArrayCache;
use yii\caching\CacheInterface;
use yii\caching\Dependency;
use yii\caching\DummyCache;

/**
 * A dependency which counts how often the version api would have been asked, so the throttling can be tested
 * without a network call.
 */
class CountingVersionCacheDependency extends VersionCacheDependency
{
    // the reusable storage of yii is static and keyed by a hash of this object, which changes with every counted
    // fetch. Turning it off keeps every evaluation in this test an actual evaluation.
    public $reusable = false;

    public int $fetches = 0;

    public int $version = 1;

    protected function fetchVersion()
    {
        $this->fetches++;

        return $this->version;
    }
}

class VersionCacheDependencyTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Dependency::resetReusableData();
    }

    protected function tearDown(): void
    {
        Dependency::resetReusableData();
        Module::setInstance(null);
        parent::tearDown();
    }

    private function createModule(array $config = []): Module
    {
        $module = new Module('flyo', null, array_merge(['token' => 'foobar'], $config));
        Module::setInstance($module);

        return $module;
    }

    private function evaluate(CountingVersionCacheDependency $dependency, CacheInterface $cache, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $dependency->evaluateDependency($cache);
        }
    }

    public function testTheIntervalIsDisabledByDefault()
    {
        $this->assertSame(0, $this->createModule()->versionCheckInterval);
    }

    public function testTheDependencyIsReusableWithinASingleRequest()
    {
        $this->assertTrue((new VersionCacheDependency())->reusable);
    }

    public function testWithoutAnIntervalEveryEvaluationAsksTheApi()
    {
        $this->createModule();
        $cache = new ArrayCache();
        $dependency = new CountingVersionCacheDependency();

        $this->evaluate($dependency, $cache, 3);

        $this->assertSame(3, $dependency->fetches);
        $this->assertSame(1, $dependency->data);
        // nothing is stored, the cache must not grow an entry which is never read
        $this->assertFalse($cache->get(VersionCacheDependency::VERSION_CACHE_KEY));
    }

    public function testAnIntervalAsksTheApiOnceAndReusesTheStoredVersion()
    {
        $this->createModule(['versionCheckInterval' => 30]);
        $cache = new ArrayCache();
        $dependency = new CountingVersionCacheDependency();

        $this->evaluate($dependency, $cache, 5);

        $this->assertSame(1, $dependency->fetches);
        $this->assertSame(1, $dependency->data);
        $this->assertSame(1, $cache->get(VersionCacheDependency::VERSION_CACHE_KEY));
    }

    public function testAVersionStoredByAnEarlierRequestIsUsedWithoutAskingTheApi()
    {
        $this->createModule(['versionCheckInterval' => 30]);
        $cache = new ArrayCache();
        $cache->set(VersionCacheDependency::VERSION_CACHE_KEY, 4711, 30);
        $dependency = new CountingVersionCacheDependency();

        $this->evaluate($dependency, $cache, 1);

        $this->assertSame(0, $dependency->fetches);
        $this->assertSame(4711, $dependency->data);
    }

    public function testANewVersionIsPickedUpOnceTheIntervalHasPassed()
    {
        $this->createModule(['versionCheckInterval' => 30]);
        $cache = new ArrayCache();
        $dependency = new CountingVersionCacheDependency();

        $this->evaluate($dependency, $cache, 2);
        $this->assertSame(1, $dependency->data);

        // the stored version expiring is what the passing of the interval looks like to the dependency
        $cache->delete(VersionCacheDependency::VERSION_CACHE_KEY);
        $dependency->version = 2;
        $this->evaluate($dependency, $cache, 1);

        $this->assertSame(2, $dependency->fetches);
        $this->assertSame(2, $dependency->data);
    }

    public function testTheChangedVersionInvalidatesTheEntry()
    {
        $this->createModule(['versionCheckInterval' => 30]);
        $cache = new ArrayCache();
        $dependency = new CountingVersionCacheDependency();

        $this->evaluate($dependency, $cache, 1);
        $this->assertFalse($dependency->isChanged($cache));

        $cache->set(VersionCacheDependency::VERSION_CACHE_KEY, 2, 30);
        $this->assertTrue($dependency->isChanged($cache));
    }

    public function testACacheWhichStoresNothingBehavesLikeNoInterval()
    {
        $this->createModule(['versionCheckInterval' => 30]);
        $dependency = new CountingVersionCacheDependency();

        $this->evaluate($dependency, new DummyCache(), 3);

        $this->assertSame(3, $dependency->fetches);
    }

    public function testTheIntervalIsIgnoredWithoutAModuleInstance()
    {
        $cache = new ArrayCache();
        $dependency = new CountingVersionCacheDependency();

        $this->evaluate($dependency, $cache, 2);

        $this->assertSame(2, $dependency->fetches);
    }
}
