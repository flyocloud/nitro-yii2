# Upgrade to 3.3

Live edit (the nitro js bridge) moved from the `Editable` widget into the module.

## What changed

- The nitro js bridge and its boot script (page refresh, scroll to block, editor handshake and the click handlers for every element with a `data-flyo-uid` attribute) are no longer registered by `Flyo\Yii\Widgets\Editable`, but by `Module::bootstrap()` on every page rendered by a web application, see `Flyo\Yii\LiveEdit`. Live edit therefore also works in projects which render `data-flyo-uid` by hand or do not use the widget at all.
- The new `Module::$liveEdit` property switches the registration on and off. `Module::getIsLiveEditEnabled()` is the single source of truth: the `Editable` widget reads the same value, so live edit is configured in one place.
- Default behavior is unchanged: live edit is enabled in every environment except production (`YII_ENV_PROD`).
- `Editable::$isEnabled` is deprecated and **has no effect anymore**. Setting it emits a deprecation notice, the widget always follows `Module::$liveEdit`.
- `Editable::ensureAssets()` has been removed.

## Module configuration

Set `liveEdit` explicitly, so it is visible in the config which environments run live edit:

```php
'modules' => [
    'flyo' => [
        'class' => \Flyo\Yii\Module::class,
        'token' => 'YOUR_TOKEN',
        'liveEdit' => !YII_ENV_PROD,
    ],
],
```

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `liveEdit` | `bool\|null` | `null` | Use `!YII_ENV_PROD` (live edit everywhere but production), `true` (also in production) or `false` (never). Leaving it `null` keeps the implicit `!YII_ENV_PROD` behavior, but set it explicitly. |
| `liveEditBridgeUrl` | `string` | `Module::LIVE_EDIT_BRIDGE_URL` | Rarely needed, only to self host the bridge js file. |

## What to do when updating

1. **Add `'liveEdit' => !YII_ENV_PROD` to your module config** (see above). Nothing breaks without it — `null` behaves the same — but the explicit value is what you change later when a production preview or a staging environment needs live edit, and it documents the behavior for the next reader.

2. **You enabled the widget in production** (any `'isEnabled' => true` passed to `Editable`/`OpenBlockInFlyo`): remove it and move the flag to the module, otherwise the marker is no longer rendered in production and the bridge is not registered either.

   ```php
   // view file
   -<?php Editable::begin(['block' => $block, 'isEnabled' => true]); ?>
   +<?php Editable::begin(['block' => $block]); ?>

   // config
   'modules' => [
       'flyo' => [
           'class' => \Flyo\Yii\Module::class,
           'token' => 'YOUR_TOKEN',
   +       'liveEdit' => true,
       ],
   ],
   ```

3. **You disabled a single widget** with `'isEnabled' => false`: there is no per widget toggle anymore, the option is ignored (with a deprecation notice). Remove it, and if live edit should be off entirely set `'liveEdit' => false` on the module.

4. **You called `Editable::getIsEnabled()`** in your own code: use `\Flyo\Yii\Module::getInstance()->getIsLiveEditEnabled()` instead, the widget method is deprecated.

5. **You called `Editable::ensureAssets()`** (or registered the assets manually): remove the call. If you need the bridge on a view which is not rendered through the module, use `\Flyo\Yii\LiveEdit::register($view)`.

6. Search your codebase for `ensureAssets`, `isEnabled` and `OpenBlockInFlyo` and apply the steps above to each hit.

## Verification

With live edit enabled, the rendered page contains a `<script src="…nitro-js-bridge…">` tag plus the inline boot script at the end of the body, and each editable block carries a `data-flyo-uid` attribute. With `'liveEdit' => false` neither script is present.

## Sitemap

`Flyo\Yii\Actions\SitemapAction` now uses the values the sitemap endpoint delivers for exactly this purpose:

- The `<loc>` of an entry is built from the `href` of the item — the url path resolved by the API — instead of the entity slug (pages) or the `routes` entry (entities). Items without a resolved `href` are skipped, duplicated hrefs are only listed once.
- Every entry with an `updated_at` timestamp now carries a `<lastmod>` value (W3C datetime, UTC), so search engines see when the content of a page actually changed. For pages this is the last time the delivered content actually changed, a rebuild with identical output does not move it.
- `flyo/nitro-php` is required in `^2.2` now, that is the version which delivers `href` and `updated_at` on the sitemap items. Run `composer update flyo/nitro-php` when updating.
- `SitemapAction::$detailRouteName` is deprecated and **has no effect anymore**, because the url is not assembled from route names. Remove it from your action configuration, it will be dropped in a future release.

  ```php
  public function actions()
  {
      return [
          'sitemap' => [
              'class' => \Flyo\Yii\Actions\SitemapAction::class,
              'domain' => 'https://example.com',
  -           'detailRouteName' => 'detail',
          ],
      ];
  }
  ```

Entities which are mapped to a route but whose route name differed from `detailRouteName` were missing from the sitemap before, they are now included. If you deliberately kept entities out of the sitemap by naming their route differently, remove the route mapping in Flyo instead.

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
