<?php

namespace Flyo\Yii\Tests;

use Flyo\Model\Block;
use Flyo\Yii\Tests\Data\AddressShape;
use Flyo\Yii\Tests\Data\PersonShape;
use Flyo\Yii\Types\Shape;
use InvalidArgumentException;
use LogicException;

class ShapeTest extends BaseTestCase
{
    public function testTheObjectOfTheResponseIsNotModified()
    {
        $content = (object) ['firstname' => 'Ada'];

        $this->assertSame($content, PersonShape::of($content));
    }

    public function testDecodedArraysAreCastedToAnObject()
    {
        $shape = PersonShape::of(['firstname' => 'Ada']);

        $this->assertSame('Ada', $shape->firstname);
    }

    public function testAMissingValueResultsInAnEmptyShape()
    {
        $shape = PersonShape::of(null);

        $this->assertInstanceOf(PersonShape::class, $shape);
        $this->assertNull($shape->firstname);
        $this->assertNull($shape->age);
        $this->assertNull($shape->address);
    }

    public function testTheBaseClassCanNotBeUsedDirectly()
    {
        $this->expectException(LogicException::class);

        Shape::of(null);
    }

    public function testATypedModelIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Read the values of a typed model with its getters instead.');

        PersonShape::of(new Block(['uid' => 'block-uid']));
    }

    public function testContentAndConfigOfABlock()
    {
        $block = new Block([
            'uid' => 'block-uid',
            'component' => 'Hero',
            'content' => (object) ['firstname' => 'Ada', 'address' => (object) ['city' => 'Bern']],
            'config' => (object) ['firstname' => 'Grace'],
        ]);

        $content = PersonShape::ofContent($block);

        $this->assertSame('Ada', $content->firstname);
        $this->assertSame('Bern', AddressShape::of($content->address)->city);
        $this->assertSame('Grace', PersonShape::ofConfig($block)->firstname);
    }

    public function testItemsOfABlock()
    {
        $block = new Block([
            'uid' => 'block-uid',
            'component' => 'Hero',
            'items' => [(object) ['firstname' => 'Ada'], (object) ['firstname' => 'Grace']],
        ]);

        $items = PersonShape::ofItems($block);

        $this->assertCount(2, $items);
        $this->assertSame(['Ada', 'Grace'], array_map(static fn (object $item): ?string => $item->firstname, $items));
        $this->assertSame([], PersonShape::ofItems(new Block(['uid' => 'block-uid'])));
    }

    public function testTheDetailDataOfAnEntity()
    {
        $entity = (object) ['model' => (object) ['firstname' => 'Ada', 'age' => 36]];

        $this->assertSame('Ada', PersonShape::ofModel($entity)->firstname);
        $this->assertSame(36, PersonShape::ofModel($entity)->age);
        $this->assertNull(PersonShape::ofModel((object) [])->firstname);
    }

    public function testListsOfAnythingElseAreEmpty()
    {
        $this->assertSame([], PersonShape::ofList(null));
        $this->assertSame([], PersonShape::ofList('string'));
    }
}
