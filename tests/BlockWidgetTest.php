<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\Block;
use Flyo\Model\BlockSlotValue;
use Flyo\Model\Page;
use Flyo\Yii\Module;
use Flyo\Yii\Tests\Data\HeroModel;
use Flyo\Yii\Widgets\BlockWidget;
use Flyo\Yii\Widgets\PageWidget;
use Flyo\Yii\Widgets\SlotRenderWidget;

class BlockWidgetTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        Module::setInstance(null);

        parent::tearDown();
    }

    public function testTheViewReceivesTheBlockAndItsUntypedJson()
    {
        Module::setInstance(new Module('flyo', null, ['token' => 'foobar']));

        $this->assertSame(
            'type:Flyo\Model\Block|uid:block-uid|headline:Hello|variant:dark',
            BlockWidget::widget(['block' => $this->block()])
        );
    }

    public function testTheWidgetRendersAnyBlockRepresentation()
    {
        Module::setInstance(new Module('flyo', null, ['token' => 'foobar']));

        $block = (object) [
            'uid' => 'std-uid',
            'component' => 'Hero',
            'content' => (object) ['headline' => 'Hello'],
            'config' => (object) ['variant' => 'light'],
        ];

        $this->assertSame(
            'type:stdClass|uid:std-uid|headline:Hello|variant:light',
            BlockWidget::widget(['block' => $block])
        );
    }

    public function testTheViewReceivesTheOwnBlockModelWhenItIsConfigured()
    {
        Module::setInstance(new Module('flyo', null, [
            'token' => 'foobar',
            'blockModels' => ['Hero' => HeroModel::class],
        ]));

        // the block is hydrated into the model of the application, the values of the model are read by the
        // accessor, therefore the widgets keep working
        $this->assertSame(
            'type:Flyo\Yii\Tests\Data\HeroModel|uid:block-uid|headline:Hello|variant:',
            BlockWidget::widget(['block' => $this->block()])
        );
    }

    public function testABlockWithoutAComponentIsNotRendered()
    {
        Module::setInstance(new Module('flyo', null, ['token' => 'foobar']));

        $this->expectExceptionMessage('Block component name which is responsible for rendering the block is not set.');

        BlockWidget::widget(['block' => new Block(['uid' => 'block-uid'])]);
    }

    public function testThePageWidgetRendersEveryBlockOfThePage()
    {
        Module::setInstance(new Module('flyo', null, ['token' => 'foobar']));

        $page = new Page(['json' => [$this->block(), $this->block()]]);

        $this->assertSame(str_repeat('type:Flyo\Model\Block|uid:block-uid|headline:Hello|variant:dark', 2), PageWidget::widget(['page' => $page]));
    }

    public function testTheSlotWidgetRendersEveryBlockOfTheSlot()
    {
        Module::setInstance(new Module('flyo', null, ['token' => 'foobar']));

        $slot = new BlockSlotValue(['identifier' => 'content', 'content' => [$this->block()]]);

        $this->assertSame('type:Flyo\Model\Block|uid:block-uid|headline:Hello|variant:dark', SlotRenderWidget::widget(['slot' => $slot]));
    }

    private function block(): Block
    {
        return new Block([
            'uid' => 'block-uid',
            'component' => 'Hero',
            'content' => (object) ['headline' => 'Hello'],
            'config' => (object) ['variant' => 'dark'],
        ]);
    }
}
