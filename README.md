# Flyo Nitro Yii2 Framework Module

[![PHPUnit](https://github.com/flyocloud/nitro-yii2/actions/workflows/phpunit.yml/badge.svg)](https://github.com/flyocloud/nitro-yii2/actions/workflows/phpunit.yml)

```sh
composer require flyo/nitro-yii2
```

add the module to your config

```php
'modules' => [
    'flyo' => [
        'class' => \Flyo\Yii\Module::class,
        'token' => 'YOUR_TOKEN',
        'liveEdit' => !YII_ENV_PROD, // this is the default behavior, set to `true` to enable live edit in production as well
    ]
]
```

add the cms page resolve to your views in the folder `/views/nitro.php`, all the routes from flyo nitro will now be resolved into this view file:

```php
<?php
use Flyo\Yii\Widgets\PageWidget;
/** @var \Flyo\Model\Page $page */
?>
<h1><?= $page->getTitle(); ?>
<?= PageWidget::widget(['page' => $page]); ?>
```

In order to render those blocks use the `Flyo\Yii\Widgets\PageWidget` which will lookup all blocks inside the folder `/views/flyo/*`, so for instance you have a `HeroTeaser` component defined in flyo the view file is stored in `/views/flyo/HeroTeaser.php` with example content:

```php
/** @var \Flyo\Model\Block $block */
print_r($block->getContent());
print_r($block->getConfig());
print_r($block->getItems());
print_r($block->getSlots());
```

The view also receives the untyped `content` and `config` of the block as variables, they are the same objects
as `$block->getContent()` and `$block->getConfig()`.

## Typed blocks and entity details

The content, the config and the items of a block are untyped json (`stdClass`), the same is true for the detail
data of an entity. Generate models from the openapi schemas of your project (`/openapi/schemas`) with the
generator of your choice, register them in the module and the widgets hand them to your views:

```php
'modules' => [
    'flyo' => [
        'class' => \Flyo\Yii\Module::class,
        'token' => 'YOUR_TOKEN',
        'blockModelNamespace' => 'App\\Flyo\\Model',
    ],
],
```

```php
<?php
// views/flyo/Hero.php

/** @var \App\Flyo\Model\BlockHero $block */
?>
<h1><?= $block->getContent()->getHeadline(); ?></h1>
```

This module does not generate anything, it only uses the models when they are available: a block component
without a model keeps the generic `Flyo\Model\Block`.

[Read more about generating, registering and hydrating your models](docs/typed-models.md)

## Layout

Generate a navigation in the layout file, use the `NavWidget`:

```php
<?php $nav = ContainerWidget::begin(['identifier' => 'main']) ?>
    <ul>
        <?php foreach ($nav->getItems() as $item): ?>
            <li><?= Html::a($item->getLabel(), $item->getHref()); ?></li>
        <?php endforeach; ?>
    </ul>
<?php $nav::end(); ?>
```

## Layout blocks with children

An example where a block contains child blocks, defined in the slot `content`:

```php
<?php
use Flyo\Yii\Widgets\BlockWidget;
/** @var \Flyo\Model\Block $block */
$config = $block->getConfig();
?>
<div class="container">
    <?php foreach ($block->getSlots()['content']->getContent() as $childBlock): ?>
        <div class="w-full">
            <?= BlockWidget::widget([
                'block' => $childBlock,
            ]); ?>
        </div>
    <?php endforeach; ?>
</div>
<?php SectionWidget::end(); ?>
```

## Extend existing Routes

Its possible to extend the routing system for existing pages. This can help when building dynamic sub pages which need to ensure that you are still on the same CMS page (not not entity detail), in order to do add the following url rule in the UrlManager section:

```php
'<path:(the-requested-slug)>/<slug:[a-z\-]+>' => 'flyo/nitro/index',
```

In order to link to extended route, its not possible to use Url::toRoute, since this is a fixed rule in routes anyhow you have to use:

```
<a href="/the-requested-slug/<?= ...; ?>">Detail</a>
```

## Live Edit

As long as the `liveEdit` module property is enabled, the module registers the Nitro JS Bridge together with its
boot script (page refresh, scroll to block, editor handshake and the click handlers for all elements with a
`data-flyo-uid` attribute) whenever a page is rendered by a web application. This happens independently of the
`Editable` widget, therefore live edit also works in projects which mark their blocks by hand or do not mark them
at all, and it is switched on and off in one single place.

## Yii2 Widget: Editable

This widget makes Flyo blocks editable inside the Flyo preview iframe by rendering the `data-flyo-uid` marker,
which is picked up by the bridge registered through [Live Edit](#live-edit).

### Usage

#### Wrap content

```php
<?php
use Flyo\Yii\Widgets\Editable;
?>

<?php Editable::begin(['block' => $block]); ?>
    <h2><?= $block->getTitle(); ?></h2>
    <p><?= $block->getText(); ?></p>
<?php Editable::end(); ?>
```
Renders:

```
<div data-flyo-uid="block-uid-here">
  <h2>…</h2>
  <p>…</p>
</div>
```

Attribute only

If you already have a wrapper element, use the static helper:

```php
<section <?= Editable::attr($block) ?>>
  <h1><?= $block->getTitle(); ?></h1>
</section>
```

Renders:

```
<section data-flyo-uid="block-uid-here">
  <h1>…</h1>
</section>
```

Notes

Highlighting/click-to-edit works only inside Flyo’s preview iframe.

Outside preview, the page behaves normally.

## Documentation

[Read More about Flyo Nitro in general](https://dev.flyo.cloud/nitro)
