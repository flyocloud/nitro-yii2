# Typed blocks and entity details

Bring your own models for the json of your flyo project: generate them from the openapi schemas endpoint with
the generator of your choice, register them in the module configuration and the widgets hand them to your
views instead of the generic `Flyo\Model\Block`.

```php
<?php
// views/flyo/Hero.php

/** @var \App\Flyo\Model\BlockHero $block */
?>
<h1><?= $block->getContent()->getHeadline(); ?></h1>
<img src="<?= Image::source($block->getContent()->getImage()->getSource(), 800, 600); ?>">
```

This module does **not** generate anything, it only uses the models when they are available.

## What is untyped

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

`content`, `config` and `items` of a block and `model` of an entity are plain `stdClass` objects, a view reads
them with property access (`$content->image`) and nothing checks those names. Your project knows the shape of
those objects, the openapi schemas endpoint of your project describes it:

```
https://api.flyo.cloud/nitro/v1/openapi/schemas?token=YOUR_TOKEN
```

## Generate the models

### openapi generator

The flyo php sdk itself is generated with the [openapi generator](https://openapi-generator.tech/), so its
models work with this module out of the box:

```json
"scripts": {
    "flyo:models": "openapi-generator-cli generate -i 'https://api.flyo.cloud/nitro/v1/openapi/schemas?token=YOUR_TOKEN' -g php -o ./generated-models --global-property models,supportingFiles,modelDocs=false,modelTests=false"
}
```

Generate the **supporting files** (`models,supportingFiles`), not only the models. With `models` alone the
generated classes declare `implements ModelInterface` and call `ObjectSerializer` in `jsonSerialize()`, but
neither of them is written, so autoloading any model fails with
`Interface OpenAPI\Client\Model\ModelInterface not found`.

### jane (no java, installable with composer)

```sh
composer require --dev jane-php/open-api-3
```

```php
<?php
// .jane-openapi

return [
    'openapi-file' => __DIR__ . '/schemas.json',
    'namespace' => 'App\\Flyo',
    'directory' => __DIR__ . '/generated',
];
```

```sh
curl -o schemas.json 'https://api.flyo.cloud/nitro/v1/openapi/schemas?token=YOUR_TOKEN'
vendor/bin/jane-openapi generate
```

Jane emits models with getters plus a `JaneObjectNormalizer`, which is a symfony serializer normalizer instead
of the static api of the openapi generator. Tell the module how to hydrate them with
[[Flyo\Yii\Module::$modelHydrator]], see below.

Any other generator works the same way: the module only needs the class name and a way to hydrate it.

## Register the models

```php
'modules' => [
    'flyo' => [
        'class' => \Flyo\Yii\Module::class,
        'token' => 'YOUR_TOKEN',

        // convention: the block component `Hero` is hydrated into App\Flyo\Model\BlockHero
        'blockModelNamespace' => 'App\\Flyo\\Model',

        // explicit, wins over the convention
        'blockModels' => [
            'Hero' => \App\Flyo\Model\BlockHero::class,
        ],

        // the detail data (`model`) of the entity type `person`
        'entityModelNamespace' => 'App\\Flyo\\Model',
        'entityModels' => [
            'person' => \App\Flyo\Model\EntityPerson::class,
        ],
    ],
],
```

| Property | Description |
| --- | --- |
| `blockModelNamespace` | Namespace of the block models, the component `Hero` resolves to `{namespace}\Block{Component}`. |
| `blockModels` | Explicit map of the component name to its model class, wins over the convention. |
| `entityModelNamespace` | Namespace of the entity models, the type `person` resolves to `{namespace}\Entity{Type}`. |
| `entityModels` | Explicit map of the entity type to the model of its detail data. |
| `modelHydrator` | Callable which turns the json into a model, defaults to the `ObjectSerializer` of the flyo sdk (openapi generator models). |

Registration is **per block**: a component without a model keeps the generic `Flyo\Model\Block`, so the models
can be introduced one block at a time and a new block in flyo does not need a regenerated model to render.

### Another generator: the model hydrator

The hydrator receives the class name and the json of the api response (a `stdClass`) and returns the model, or
null to keep the untyped data. For jane:

```php
use App\Flyo\Normalizer\JaneObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

'modelHydrator' => function (string $class, mixed $data): ?object {
    $serializer = new Serializer([new JaneObjectNormalizer()]);

    // jane denormalizes arrays, the module hands over the json object of the response
    return $serializer->denormalize(json_decode(json_encode($data), true), $class);
},
```

With a hydrator configured the module only checks that the class exists, everything else is up to you. Without
one, a class is only used when it provides the static api of an openapi generator model (`DISCRIMINATOR`,
`openAPITypes()`, `setters()`, `attributeMap()`, `isNullable()`), otherwise the untyped data is used and a
warning is logged instead of failing with a fatal error.

## What the widgets pass to your views

| View | Variable | Value |
| --- | --- | --- |
| `views/flyo/<Component>.php` | `block` | the model of the component, or `Flyo\Model\Block` when there is none |
| | `content` | the content of the block, typed when a model is registered |
| | `config` | the config of the block, typed when a model is registered |
| entity detail view | `entity` | the `Flyo\Model\Entity` |
| | `model` | the detail data, typed when a model is registered |

## Room for models which are not `Flyo\Model\Block`

A generated model lives in **your** namespace and can not extend `Flyo\Model\Block`, therefore the widgets of
this module do not type hint the sdk models anymore. They read the values of a block through
`Flyo\Yii\Accessor`, which looks up a getter first, then a public property, then the array access of the
object:

| Before | Now |
| --- | --- |
| `BlockWidget::$block`, `Editable::$block` (`Flyo\Model\Block`) | `object` |
| `Editable::attr(Block $block)`, `OpenBlockInFlyo::attr(Block $block)` | `attr(object $block)` |
| `PageWidget::$page` (`Flyo\Model\Page`) | `object` |
| `SlotRenderWidget::$slot` (`Flyo\Model\BlockSlotValue`) | `object` |

That makes the sdk models, your generated models and the raw json interchangeable, so live edit keeps working
with your own models:

```php
<section <?= Editable::attr($block); ?>>
```

In your own code, widen the same type hints where a block is passed around, for example in your own widgets:

```php
-public Block $block;
+public object $block;
```

If you want to keep a narrow type hint, use your own model class:

```php
public BlockHero $block;
```

## Costs and fallbacks

Hydrating a model means the block is serialized and hydrated again on every render, which is a real cost on a
page with many blocks. What you get for it are typed getters, and everything the type information enables:
autocompletion, phpstan and a failing static analysis when a view reads a field a block does not have anymore.

If you want the type information without that cost, do not register the model but annotate the untyped json in
the view, provided your generator emits public properties:

```php
<?php
/** @var \App\Flyo\Model\BlockHeroContent $content */
?>
<h1><?= $content->headline; ?></h1>
```

The module never fails because of a model:

- a component or an entity type without a model keeps the untyped data,
- a class which is configured but missing (or which the default hydrator does not understand) logs a warning
  and keeps the untyped data,
- a hydration which fails — models which have not been regenerated after a schema change — is rethrown in
  debug mode and logged with a fallback to the untyped data in production.
