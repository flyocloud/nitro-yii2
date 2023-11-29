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

## Documentation

[Read More about Flyo Nitro in general](https://dev.flyo.cloud/nitro)
