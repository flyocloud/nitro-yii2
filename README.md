# Flyo Nitro CMS Yii2

```
composer require flyo/nitrocms-yii2
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

add the cms page resolve to your views in the folder `/views/cms.php`, all the routes from flyo nitro will now be resolved into this view file:

```php
/** @var \Flyo\Model\Page $page */
<h1><?= $page->getTitle(); ?></h1>
Flyo\Yii\Widgets\PageWidget::widget(['page' => $page]);
```

In order to render those blocks use the `Flyo\Yii\Widgets\PageWidget` which will lookup all blocks inside the folder `/views/flyo/*`, so for instance you have a `HeroTeaser` component defined in flyo the view file is stored in `/views/flyo/HeroTeaser.php` with example content:

```php
/** @var \Flyo\Model\Block $block */
print_r($block->getContent());
print_r($block->getConfig());
print_r($block->getItems());
print_r($block->getSlots());
```