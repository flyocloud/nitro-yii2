<?php

namespace Flyo\Yii\Cache;

use Flyo\Yii\Module;
use yii\caching\Dependency;

/**
 * Invalidates a cache entry as soon as the content version in flyo has changed.
 *
 * Yii evaluates a dependency on every read of a cache entry, so by default every request which reaches the origin
 * asks the version api before it may use a cache entry it already has. Set [[Module::$versionCheckInterval]] to reuse
 * that answer for a few seconds on sites where the calls add up, the property documents from which traffic level on
 * that is worth the loss of precision.
 */
class VersionCacheDependency extends Dependency
{
    /**
     * @var array The cache key of the throttled version, only used when [[Module::$versionCheckInterval]] is set.
     */
    public const VERSION_CACHE_KEY = ['flyo', 'version'];

    public $reusable = true;

    protected function generateDependencyData($cache)
    {
        $interval = Module::getInstance()?->versionCheckInterval ?? 0;

        if ($interval <= 0) {
            return $this->fetchVersion();
        }

        // $cache is the very cache component which is evaluating this dependency, therefore the interval can not
        // introduce a requirement of its own: whatever caches the entry also stores the version. A cache which keeps
        // nothing (dummy cache) answers with `false` every time and falls back to asking the api on every evaluation.
        $version = $cache->get(self::VERSION_CACHE_KEY);

        if ($version === false) {
            $version = $this->fetchVersion();
            $cache->set(self::VERSION_CACHE_KEY, $version, $interval);
        }

        return $version;
    }

    /**
     * The current content version of the flyo project.
     *
     * @return int
     */
    protected function fetchVersion()
    {
        return Module::getVersionApi()->getVersion();
    }
}
