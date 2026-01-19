# Upgrade to 3.0

- The minimum supported PHP version is now PHP 8.3.

# Upgrade to 2.0

## Breaking Cache Configuration

- `Module::$cacheDuration` has been removed. The module no longer mixes the duration of server-side caching, CDN headers, and client-side caching in a single property.
- Server-side page caching is still controlled by the boolean `Module::$serverPageCache`, but the cache lifetime is now defined separately via `Module::$serverPageCacheDuration` (default `3600`). Update any overrides or configs that previously set `cacheDuration` to the new name so that `PageCache` and the config cache share the same TTL.
- CDN caching is opt-in through the new `Module::$cdnCache` flag. When enabled, `Vercel-CDN-Cache-Control` and `CDN-Cache-Control` headers are emitted with the `Module::$cdnCacheDuration` value (`1800` by default). Adjust those durations (or disable the headers entirely) per your CDN setup.
- Client/browser caching keeps using `Module::$clientHttpCache`, but the duration is now configurable via `Module::$clientHttpCacheDuration` (also `1800` by default).

## What to do when updating

1. Replace any configuration that sets `cacheDuration` with the new properties. Example:

   ```php
   'modules' => [
       'flyo' => [
           'class' => \Flyo\Yii\Module::class,
-          'cacheDuration' => 1209600,
+          'serverPageCacheDuration' => 1209600,
+          'cdnCacheDuration' => 1800,
+          'clientHttpCacheDuration' => 1800,
       ],
   ],
   ```

2. If you previously turned off server-side caching by setting `serverPageCache` to `false`, no further change is required; the new duration property is only read when caching is enabled.
3. If you relied on the CDN headers for downstream caching, update your expectations to use `cdnCacheDuration` and optionally disable the header emission entirely with `'cdnCache' => false`.
4. Search your codebase for `cacheDuration` (module configs, tests, extensions) and migrate each usage to the appropriate new property to avoid undefined property errors in 2.0.

## Verification

Run your existing HTTP cache integration tests or smoke tests to confirm that the new headers and durations behave as expected. If you rely on a CDN edge cache, verify that the emitted headers match the new `cdnCacheDuration` value and adjust accordingly.
