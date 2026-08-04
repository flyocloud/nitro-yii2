# Type specs for blocks and entity details

Bring your own types for the json of your flyo project: `yii flyo/types/generate` writes one php class per
block component and per entity type, so your ide autocompletes `$content->headline` and phpstan tells you
when a block schema and a view file drift apart.

```sh
./yii flyo/types/generate
```

```php
<?php
// views/flyo/Hero.php

use app\models\flyo\BlockHero;

/** @var \Flyo\Model\Block $block */
$content = BlockHero::content($block);
?>
<h1><?= $content->headline; ?></h1>
<img src="<?= Image::source($content->image->source, 800, 600); ?>">
```

## The constraint which decides the design

The json of a block is typed by the sdk down to the point where your project specific data starts:

```php
protected static $openAPITypes = [
    'items' => 'mixed[]',
    'content' => 'object',   // <- your block fields live here
    'config' => 'object',    // <- and here
    'identifier' => 'string',
    'uid' => 'string',
    'component' => 'string',
    'slots' => 'array<string,\Flyo\Model\BlockSlotValue>',
];
```

`content`, `config`, `items` of a block and `model` of an entity are plain `stdClass` objects, so a view reads
them with property access (`$content->image`) and nothing checks those names.

The obvious idea is to generate real models with the openapi generator and to hand them to the views instead
of `Flyo\Model\Block`. That collides with the type hints of this module and of your own widgets: a generated
class lives in **your** namespace and can not extend `Flyo\Model\Block`, therefore every
`Editable::attr($block)` and every `'block' => $block` widget would raise a `TypeError`. It also means every
view has to migrate from `$content->image` to `$content->getImage()`, and every block gets serialized and
hydrated again on every single render.

A next.js frontend does not have that problem because its types are erased: `block: BlockHero` costs nothing
at runtime, the object stays the parsed json. The php equivalent of that is not "hydrate a different class",
it is **describe the object you already have** — and that is what the generated type specs do.

## What is generated

The command downloads the openapi schemas of your project (`/openapi/schemas`) and writes two kinds of classes
into [[Flyo\Yii\Module::$typesPath]]:

| Kind | Example | What it is |
| --- | --- | --- |
| Shape | `BlockHeroContent`, `EntityPerson` | One public property per json key. It **describes** the `stdClass` of the api response, it does not replace it. |
| Accessor | `BlockHero` | One static method per untyped part of a block (`content()`, `config()`, `items()`, `slots()`) plus the `COMPONENT` constant. |

```php
class BlockHeroContent extends Shape
{
    /**
     * The headline of the hero.
     */
    public ?string $headline = null;

    public ?BlockHeroContentImage $image = null;

    /**
     * @var array<int, string>|null
     */
    public ?array $tags = null;
}
```

```php
final class BlockHero
{
    public const COMPONENT = 'Hero';

    /**
     * @return BlockHeroContent
     */
    public static function content(object $block): mixed
    {
        return BlockHeroContent::ofContent($block);
    }
}
```

`BlockHero::content($block)` returns the very same `stdClass` object the api returned, the return type only
exists for your tooling. No serialization, no hydration, no copy, no `TypeError`, and a block which has no
type spec keeps rendering exactly as before.

## Setup

1. Configure the namespace and the folder of the generated classes:

   ```php
   'modules' => [
       'flyo' => [
           'class' => \Flyo\Yii\Module::class,
           'token' => 'YOUR_TOKEN',
           'typesNamespace' => 'app\\models\\flyo',
           'typesPath' => '@app/models/flyo',
       ],
   ],
   ```

2. Make sure the namespace is autoloaded, the default `app\` prefix of the basic and advanced application
   template already covers `app\models\flyo`.

   The command is part of the flyo module, so the module has to be in the configuration of your console
   application as well — the shared configuration of the basic and the advanced application template already
   is. Alternatively register only the command:

   ```php
   // config/console.php
   'controllerMap' => [
       'flyo-types' => [
           'class' => \Flyo\Yii\Controllers\TypesController::class,
       ],
   ],
   ```

   ```sh
   ./yii flyo-types/generate
   ```

   The command looks the module up in the application configuration, therefore the token, the namespace and
   the path are read from the module in both cases.

3. Regenerate the type specs whenever your blocks change, for example as a composer script:

   ```json
   "scripts": {
       "flyo:types": "./yii flyo/types/generate --clean"
   }
   ```

| Option | Description |
| --- | --- |
| `--namespace`, `-n` | Namespace of the generated classes, defaults to `Module::$typesNamespace`. |
| `--path`, `-p` | Output folder, defaults to `Module::$typesPath`. |
| `--schemaFile`, `-f` | Read the openapi document from a local json file instead of the api, useful in a ci pipeline. |
| `--url`, `-u` | Another schemas endpoint. |
| `--mapClass` | Name of the generated component map, empty disables it. |
| `--clean` | Delete generated files of a previous run which are not generated anymore. Only files with the generated marker are removed. |
| `--dryRun` | List the files instead of writing them. |

The generated files are readable php without any dependency on the openapi generator, so you can either commit
them or generate them during your build.

## Using the type specs

### In a block view

```php
<?php
// views/flyo/Hero.php

use app\models\flyo\BlockHero;
use app\models\flyo\BlockHeroConfig;
use Flyo\Yii\Widgets\Editable;

/** @var \Flyo\Model\Block $block */
$content = BlockHero::content($block);
$config = BlockHero::config($block);
?>
<section <?= Editable::attr($block); ?> class="<?= $config->variant === BlockHeroConfig::VARIANT_DARK ? 'dark' : ''; ?>">
    <h1><?= $content->headline; ?></h1>

    <?php foreach (BlockHero::items($block) as $item): ?>
        <li><?= $item->label; ?></li>
    <?php endforeach; ?>
</section>
```

Every block view also receives the untyped `content` and `config` of the block as variables, so the shape can
be applied with a plain doc block if you prefer that over the accessor:

```php
<?php
/** @var \app\models\flyo\BlockHeroContent $content */
?>
<h1><?= $content->headline; ?></h1>
```

### In an entity detail view

The detail data of an entity (`Entity::getModel()`) is untyped as well, the entity action passes it as `model`
variable:

```php
<?php
// views/site/person.php

use app\models\flyo\EntityPerson;

/** @var \Flyo\Model\Entity $entity */
$person = EntityPerson::ofModel($entity);
?>
<h1><?= $person->firstname; ?> <?= $person->lastname; ?></h1>
<p><?= $person->address->city; ?></p>
```

### In your own code

Anywhere you get a block, a slot or an entity you can apply a shape to its untyped parts:

| Call | Returns |
| --- | --- |
| `BlockHeroContent::ofContent($block)` | the content of a block |
| `BlockHeroConfig::ofConfig($block)` | the config of a block |
| `HeroItem::ofItems($block)` | the items of a block, as list |
| `EntityPerson::ofModel($entity)` | the detail data of an entity |
| `BlockHeroContent::of($anything)` | any untyped value, for example a nested object |
| `Blocks::COMPONENTS` | the component name of every block, mapped to its type spec class |

## Every property is nullable

A shape describes json, and a key which is missing in an api response is simply not there. Therefore all
properties are declared nullable and a shape for a value which does not exist at all returns an empty
instance:

```php
$content = BlockHeroContent::ofContent($blockWithoutContent);

$content->headline; // null, not an error
```

That is deliberate: the type spec should be the reason a view breaks *never*, and phpstan already tells you
where a value needs a null check.

## When a block changes

Nothing breaks. The json arrives as before and every view keeps rendering, the type specs are only out of
date until the next `yii flyo/types/generate`. What changes:

- a new field is not known to your ide until you regenerate,
- a removed field is reported by phpstan as an access to an undefined property once you regenerate, which is
  exactly the list of views you have to fix.

## Alternative: hydrate your own openapi models

If you prefer real objects with getters over the erased types, the module hydrates every block into your own
generated model. This is opt in and can be combined per component:

```php
'modules' => [
    'flyo' => [
        'class' => \Flyo\Yii\Module::class,
        'token' => 'YOUR_TOKEN',
        // convention: the component `Hero` is hydrated into OpenAPI\Client\Model\BlockHero
        'blockModelNamespace' => 'OpenAPI\\Client\\Model',
        // or explicit, wins over the convention
        'blockModels' => [
            'Hero' => \OpenAPI\Client\Model\BlockHero::class,
        ],
        // the detail data of the entity type `person`
        'entityModels' => [
            'person' => \OpenAPI\Client\Model\EntityPerson::class,
        ],
    ],
],
```

Generate the models with the **supporting files**, otherwise `ModelInterface` and `ObjectSerializer` are
missing and autoloading a model fails with `Interface OpenAPI\Client\Model\ModelInterface not found`:

```json
"scripts": {
    "flyo:models": "openapi-generator-cli generate -i https://api.flyo.cloud/nitro/v1/openapi/schemas?token=YOUR_TOKEN -g php -o ./generated-models --global-property models,supportingFiles,modelDocs=false,modelTests=false"
}
```

What to expect:

- your block views receive your model instead of `Flyo\Model\Block`, the values are read with getters
  (`$block->getContent()->getImage()`), and `$block instanceof \Flyo\Model\Block` is false,
- the widgets of this module accept it (they read the values of any block representation), but **your own**
  widgets and helpers have to widen their `Flyo\Model\Block` type hints,
- every rendered block is serialized and hydrated once, which is a real cost on a page with many blocks,
- a block whose model does not exist keeps the sdk model, and a hydration which fails is logged and falls back
  to the sdk model outside of debug mode, so an outdated model never takes production down.

The two approaches solve the same problem at different prices, the following table summarizes it:

| | Generated type specs | Own openapi models |
| --- | --- | --- |
| What the view receives | `Flyo\Model\Block` + `stdClass` json | your model |
| Reading a value | `$content->headline` | `$block->getContent()->getHeadline()` |
| Runtime cost | none | serialize and hydrate per block |
| Migration of existing views | one line per view | every property access |
| Toolchain | this module | openapi generator (java) |
| Missing type spec / model | renders as before | renders as before |

## How the module reads a block

Every widget and action of this module reads the values of a block through `Flyo\Yii\Types\Accessor`, which
looks up a getter first, then a public property, then the array access of the object. That is what makes the
untyped `stdClass`, the sdk models and your own generated models interchangeable, and it is the reason why the
type hints of `BlockWidget::$block`, `Editable::$block`, `PageWidget::$page` and `SlotRenderWidget::$slot` are
`object` instead of a concrete class.

## What the module passes to a view

| View | Variable | Value |
| --- | --- | --- |
| `views/flyo/<Component>.php` | `block` | the block, either the sdk model or your own model |
| | `content` | the untyped content of the block |
| | `config` | the untyped config of the block |
| entity detail view | `entity` | the `Flyo\Model\Entity` |
| | `model` | the detail data, either untyped or your own model |
