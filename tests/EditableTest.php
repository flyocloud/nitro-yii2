<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\Block;
use Flyo\Yii\Module;
use Flyo\Yii\Tests\Data\HeroModel;
use Flyo\Yii\Widgets\Editable;

class EditableTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        Module::setInstance(null);

        parent::tearDown();
    }

    public function testMarkerFollowsTheModuleLiveEditSetting()
    {
        $this->setModuleInstance(true);

        $this->assertSame('<div data-flyo-uid="block-uid"></div>', $this->render());

        $this->setModuleInstance(false);

        $this->assertSame('', $this->render());
    }

    public function testDeprecatedIsEnabledHasNoEffect()
    {
        $this->setModuleInstance(false);

        $this->assertSame('', $this->render(['isEnabled' => true]));

        $this->setModuleInstance(true);

        $this->assertSame('<div data-flyo-uid="block-uid"></div>', $this->render(['isEnabled' => false]));
    }

    public function testDeprecatedGetIsEnabledReturnsTheModuleLiveEditSetting()
    {
        $this->setModuleInstance(false);

        $widget = new Editable(['block' => new Block(['uid' => 'block-uid'])]);
        $this->assertFalse($widget->getIsEnabled());

        // the widget started an output buffer in init(), close it again
        ob_end_clean();
    }

    public function testTheMarkerWorksWithAnyBlockRepresentation()
    {
        $this->setModuleInstance(true);

        // the sdk model, an own generated model and the raw json all provide the uid of the block
        foreach ([new Block(['uid' => 'block-uid']), new HeroModel(), (object) ['uid' => 'block-uid']] as $block) {
            if ($block instanceof HeroModel) {
                $block->setUid('block-uid');
            }

            $this->assertSame('data-flyo-uid="block-uid"', Editable::attr($block));
            $this->assertSame('<div data-flyo-uid="block-uid"></div>', $this->render(['block' => $block]));
        }
    }

    public function testABlockWithoutUidRendersAnEmptyMarker()
    {
        $this->setModuleInstance(true);

        $this->assertSame('data-flyo-uid=""', Editable::attr(new Block([])));
    }

    private function setModuleInstance(bool $liveEdit): void
    {
        Module::setInstance(new Module('flyo', null, ['token' => 'foobar', 'liveEdit' => $liveEdit]));
    }

    private function render(array $config = []): string
    {
        ob_start();
        Editable::begin(array_merge(['block' => new Block(['uid' => 'block-uid'])], $config));
        Editable::end();

        return ob_get_clean();
    }
}
