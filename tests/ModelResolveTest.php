<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\Block;
use Flyo\Model\Entity;
use Flyo\Model\EntityInterface;
use Flyo\Yii\Module;
use Flyo\Yii\Tests\Data\BlockHero;
use Flyo\Yii\Tests\Data\BrokenModel;
use Flyo\Yii\Tests\Data\EntityPerson;
use Flyo\Yii\Tests\Data\HeroModel;
use Flyo\Yii\Tests\Data\PersonModel;
use Flyo\Yii\Tests\Data\PersonShape;
use RuntimeException;

class ModelResolveTest extends BaseTestCase
{
    public function testWithoutAnyConfigurationTheBlockIsNotTouched()
    {
        $block = $this->block();

        $this->assertSame($block, $this->module()->resolveBlockModel($block));
    }

    public function testTheExplicitMapWinsOverTheConvention()
    {
        $module = $this->module([
            'blockModelNamespace' => 'Flyo\\Yii\\Tests\\Data',
            'blockModels' => ['Hero' => HeroModel::class],
        ]);

        $this->assertInstanceOf(HeroModel::class, $module->resolveBlockModel($this->block()));
    }

    public function testTheConventionResolvesTheModelOfTheComponent()
    {
        $module = $this->module(['blockModelNamespace' => 'Flyo\\Yii\\Tests\\Data']);
        $model = $module->resolveBlockModel($this->block());

        $this->assertInstanceOf(BlockHero::class, $model);
        $this->assertSame('block-uid', $model->getUid());
        $this->assertSame('Hello', $model->getContent()->headline);
    }

    public function testABlockWithoutAModelKeepsTheSdkModel()
    {
        $module = $this->module(['blockModelNamespace' => 'Flyo\\Yii\\Tests\\Data']);
        $block = new Block(['uid' => 'block-uid', 'component' => 'DoesNotExist']);

        $this->assertSame($block, $module->resolveBlockModel($block));
    }

    public function testAClassWhichIsNoOpenapiModelIsIgnored()
    {
        $module = $this->module(['blockModels' => ['Hero' => PersonShape::class]]);
        $block = $this->block();

        $this->assertSame($block, $module->resolveBlockModel($block));
    }

    public function testAFailingHydrationIsReportedInDebugMode()
    {
        $module = $this->module(['blockModels' => ['Hero' => BrokenModel::class]]);

        // YII_DEBUG is enabled while testing, in production the exception is logged and the untyped data is
        // used instead, so a schema change does not break the rendering
        $this->assertTrue(YII_DEBUG);
        $this->expectException(RuntimeException::class);

        $module->resolveBlockModel($this->block());
    }

    public function testTheDetailDataOfAnEntityIsHydratedIntoTheOwnModel()
    {
        $module = $this->module(['entityModels' => ['person' => PersonModel::class]]);
        $model = $module->resolveEntityModel($this->entity());

        $this->assertInstanceOf(PersonModel::class, $model);
        $this->assertSame('Ada', $model->getFirstname());
        $this->assertSame(36, $model->getAge());
    }

    public function testTheEntityConventionResolvesTheModelOfTheType()
    {
        $module = $this->module(['entityModelNamespace' => 'Flyo\\Yii\\Tests\\Data']);

        $this->assertInstanceOf(EntityPerson::class, $module->resolveEntityModel($this->entity('person')));
    }

    public function testWithoutAModelTheUntypedDetailDataIsReturned()
    {
        $entity = $this->entity();

        $this->assertSame($entity->getModel(), $this->module()->resolveEntityModel($entity));
        $this->assertSame('Ada', $this->module()->resolveEntityModel($entity)->firstname);
    }

    public function testAnEntityWithoutTypeIsNotHydrated()
    {
        $module = $this->module(['entityModels' => ['person' => PersonModel::class]]);
        $entity = $this->entity('');

        $this->assertSame($entity->getModel(), $module->resolveEntityModel($entity));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function module(array $config = []): Module
    {
        return new Module('flyo', null, array_merge(['token' => 'foobar'], $config));
    }

    private function block(): Block
    {
        return new Block([
            'uid' => 'block-uid',
            'component' => 'Hero',
            'content' => (object) ['headline' => 'Hello'],
        ]);
    }

    private function entity(string $type = 'person'): Entity
    {
        return new Entity([
            'entity' => new EntityInterface(['entity_type' => $type]),
            'model' => (object) ['firstname' => 'Ada', 'age' => 36],
        ]);
    }
}
