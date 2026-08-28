<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\Entity;
use Flyo\Model\EntityInterface;
use Flyo\Yii\Actions\EntityAction;
use Flyo\Yii\Module;
use yii\console\Controller;

class EntityActionTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        Module::setInstance(null);

        parent::tearDown();
    }

    private function createEntity(bool $isDraft, ?int $draftExpiresAt = null): Entity
    {
        return new Entity([
            'entity' => new EntityInterface([
                'entity_title' => 'A title',
                'entity_teaser' => 'A teaser',
            ]),
            'is_draft' => $isDraft,
            'draft_expires_at' => $draftExpiresAt,
        ]);
    }

    private function runAction(Entity $entity): Module
    {
        $module = new Module('flyo', null, ['token' => 'foobar']);
        Module::setInstance($module);

        $controller = new class ('test', $this->app) extends Controller {
            public function render($view, $params = [])
            {
                return 'rendered';
            }
        };

        $action = new EntityAction('detail', $controller, ['finder' => fn () => $entity]);
        $this->assertSame('rendered', $action->run());

        return $module;
    }

    public function testDraftEntityDisablesEveryCache()
    {
        $module = $this->runAction($this->createEntity(true, 1770000000));

        $this->assertTrue($module->getIsCacheDisabled());
        $this->assertFalse($module->serverPageCache);
        $this->assertFalse($module->cdnCache);
        $this->assertFalse($module->clientHttpCache);
    }

    public function testLiveEntityKeepsTheCacheEnabled()
    {
        $module = $this->runAction($this->createEntity(false));

        $this->assertFalse($module->getIsCacheDisabled());
        $this->assertTrue($module->serverPageCache);
        $this->assertTrue($module->cdnCache);
        $this->assertTrue($module->clientHttpCache);
    }

    public function testEntityWithoutDraftInformationKeepsTheCacheEnabled()
    {
        $entity = new Entity([
            'entity' => new EntityInterface(['entity_title' => 'A title', 'entity_teaser' => 'A teaser']),
        ]);

        $this->assertFalse($this->runAction($entity)->getIsCacheDisabled());
    }
}
