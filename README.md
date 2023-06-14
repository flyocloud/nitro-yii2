# Flyo Nitro Yii2 Framework Module

```sh
composer require flyo/nitro-yii2
```

add the module to your config

```php
'modules' => [
    'flyo' => [
        'class' => \Flyo\Yii\Module::class,
        'token' => 'YOUR_TOKEN',
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

## Layout

Generate a navigation in the layout file, use the `NavWidget`:

```php
<?php $nav = NavWidget::begin(); ?>
    <ul>
        <?php foreach ($nav->getItems() as $item): ?>
            <li><?= Html::a($item->getLabel(), $item->getPath()); ?></li>
        <?php endforeach; ?>
    </ul>
<?php $nav::end(); ?>
```

## Documentation

[Read More in the Docs](https://dev.flyo.cloud/nitro/php)

## Extend existing Routes

Its possible to extend the routing system for existing pages, in order to do so use:

```php
'<path:(the-page-path-to-extend)>/<slug:[a-z\-]+>' => 'flyo/nitro/index'
```

In order to link to this extend Route, the below example could be used:

```php
yii\helpers\Url::toRoute(['/flyo/nitro/index', 'path' => Flyo\Yii\Module::getInstance()->currentPage->getSlug(), 'slug' => 'this-is-the-slug-param']);
```