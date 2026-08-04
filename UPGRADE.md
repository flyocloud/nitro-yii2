# Upgrade to 3.4

Type specs for blocks and entity details, see [docs/type-specs.md](docs/type-specs.md). Nothing has to be
changed in an existing project, everything below is either additive or a widened type hint.

## What changed

- New console command `yii flyo/types/generate` which generates a php class for every block component and
  every entity type of your project from the openapi schemas of the nitro api. The generated classes describe
  the untyped json (`content`, `config`, `items` of a block, `model` of an entity), they do not replace it,
  therefore they have no runtime cost. Configure them with the new `Module::$typesNamespace` and
  `Module::$typesPath` properties.
- New optional hydration of your **own** generated openapi models with `Module::$blockModelNamespace`,
  `Module::$blockModels`, `Module::$entityModelNamespace` and `Module::$entityModels`. Without those properties
  the views receive the sdk models exactly as before.
- The widgets do not type hint the sdk models anymore, they read the values of a block through the new
  `Flyo\Yii\Types\Accessor` (getter, public property or array access). The following type hints changed from a
  concrete class to `object`:

  | Before | Now |
  | --- | --- |
  | `BlockWidget::$block`, `Editable::$block` (`Flyo\Model\Block`) | `object` |
  | `Editable::attr(Block $block)`, `OpenBlockInFlyo::attr(Block $block)` | `attr(object $block)` |
  | `PageWidget::$page` (`Flyo\Model\Page`) | `object` |
  | `SlotRenderWidget::$slot` (`Flyo\Model\BlockSlotValue`) | `object` |

  Passing the sdk models keeps working, the widgets accept your own models and the raw json in addition.
- A block view receives two additional variables next to `block`: `content` and `config`, the untyped json of
  the block.
- The `EntityAction` passes the detail data of the entity as additional `model` variable to the view.

## What to do when updating

1. Nothing, if you do not want type specs. All changes are backwards compatible.

2. **You extend `Editable` or `OpenBlockInFlyo` and override `attr()`**: widen the parameter of your override
   to `object` as well, php requires the parameter type of an override to be identical or wider.

   ```php
   -public static function attr(Block $block): string
   +public static function attr(object $block): string
   ```

3. **You want type specs**: add `typesNamespace` and `typesPath` to your module configuration, run
   `./yii flyo/types/generate` and start using the generated classes in the views you touch anyway:

   ```php
   'modules' => [
       'flyo' => [
           'class' => \Flyo\Yii\Module::class,
           'token' => 'YOUR_TOKEN',
   +       'typesNamespace' => 'app\\models\\flyo',
   +       'typesPath' => '@app/models/flyo',
       ],
   ],
   ```

   ```php
   // views/flyo/Hero.php
   +use app\models\flyo\BlockHero;
   +
   +$content = BlockHero::content($block);
   -<h1><?= $block->getContent()->headline; ?></h1>
   +<h1><?= $content->headline; ?></h1>
   ```

4. **You already generate your own openapi models** and want the views to receive them: configure
   `blockModelNamespace` or `blockModels` and generate the models including the supporting files
   (`--global-property models,supportingFiles`), otherwise `ModelInterface` and `ObjectSerializer` are missing
   and autoloading a model fails. Your own widgets which type hint `Flyo\Model\Block` have to be widened,
   see [docs/type-specs.md](docs/type-specs.md).

## Verification

`./yii flyo/types/generate --dryRun` lists the classes which would be generated without writing them. After
generating, `vendor/bin/phpstan` reports every view which reads a field a block does not have anymore.

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
