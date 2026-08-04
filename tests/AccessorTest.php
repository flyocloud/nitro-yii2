<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\Block;
use Flyo\Model\BlockSlotValue;
use Flyo\Model\Page;
use Flyo\Yii\Tests\Data\HeroModel;
use Flyo\Yii\Types\Accessor;

class AccessorTest extends BaseTestCase
{
    public function testReadsTheSdkModelThroughItsGetters()
    {
        $block = new Block([
            'uid' => 'block-uid',
            'identifier' => 'hero',
            'component' => 'Hero',
            'content' => (object) ['headline' => 'Hello'],
            'config' => (object) ['variant' => 'dark'],
            'items' => [(object) ['label' => 'one']],
            'slots' => ['content' => new BlockSlotValue(['identifier' => 'content', 'content' => []])],
        ]);

        $this->assertSame('block-uid', Accessor::uid($block));
        $this->assertSame('hero', Accessor::identifier($block));
        $this->assertSame('Hero', Accessor::component($block));
        $this->assertSame('Hello', Accessor::content($block)->headline);
        $this->assertSame('dark', Accessor::config($block)->variant);
        $this->assertSame('one', Accessor::items($block)[0]->label);
        $this->assertSame(['content'], array_keys(Accessor::slots($block)));
        $this->assertInstanceOf(BlockSlotValue::class, Accessor::slot($block, 'content'));
        $this->assertNull(Accessor::slot($block, 'nope'));
    }

    public function testReadsSnakeCaseKeysThroughTheirCamelCaseGetter()
    {
        $page = new Page(['is_home' => 1, 'meta_json' => null]);

        $this->assertSame(1, Accessor::read($page, 'is_home'));
        $this->assertSame('fallback', Accessor::read($page, 'meta_json', 'fallback'));
    }

    public function testReadsPlainObjects()
    {
        $block = (object) ['uid' => 'std-uid', 'component' => 'Hero', 'content' => (object) ['headline' => 'Hello']];

        $this->assertSame('std-uid', Accessor::uid($block));
        $this->assertSame('Hero', Accessor::component($block));
        $this->assertSame('Hello', Accessor::content($block)->headline);
        $this->assertSame('', Accessor::identifier($block));
        $this->assertSame([], Accessor::items($block));
        $this->assertSame('fallback', Accessor::read($block, 'nope', 'fallback'));
    }

    public function testReadsForeignGeneratedModels()
    {
        $block = (new HeroModel())->setUid('model-uid')->setComponent('Hero')->setContent((object) ['headline' => 'Hello']);

        $this->assertSame('model-uid', Accessor::uid($block));
        $this->assertSame('Hero', Accessor::component($block));
        $this->assertSame('Hello', Accessor::content($block)->headline);
    }

    public function testDoesNotExposePrivateProperties()
    {
        $object = new class () {
            private string $secret = 'private';

            public string $visible = 'public';
        };

        $this->assertNull(Accessor::read($object, 'secret'));
        $this->assertSame('public', Accessor::read($object, 'visible'));
    }

    public function testReadsTheBlocksOfAPageAndOfASlot()
    {
        $block = new Block(['uid' => 'block-uid', 'component' => 'Hero']);

        $this->assertSame([$block], Accessor::blocks(new Page(['json' => [$block]])));
        $this->assertSame([$block], Accessor::blocks(new BlockSlotValue(['identifier' => 'content', 'content' => [$block]])));
        $this->assertSame([], Accessor::blocks(new Page(['json' => []])));
        $this->assertSame([], Accessor::blocks((object) []));
    }

    public function testReadsTheDetailDataOfAnEntity()
    {
        $entity = (object) ['model' => (object) ['firstname' => 'Ada']];

        $this->assertSame('Ada', Accessor::model($entity)->firstname);
    }
}
