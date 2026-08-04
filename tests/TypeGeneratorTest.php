<?php

namespace Flyo\Yii\Tests;

use Flyo\Yii\Types\SchemaDocument;
use Flyo\Yii\Types\TypeGenerator;
use InvalidArgumentException;

class TypeGeneratorTest extends BaseTestCase
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $files = null;

    /**
     * @return array<string, string>
     */
    private function generate(): array
    {
        if (self::$files === null) {
            self::$files = (new TypeGenerator($this->document(), ['namespace' => 'app\\models\\flyo']))->generate();
        }

        return self::$files;
    }

    private function document(): SchemaDocument
    {
        return SchemaDocument::fromFile(__DIR__ . '/Data/schemas.json');
    }

    public function testOneFilePerSchemaAndOnePerInlineObject()
    {
        $this->assertSame([
            // the blocks, one accessor per component
            'BlockHTML.php',
            'BlockHTMLContent.php',
            'BlockHero.php',
            // the inline `config` object of the hero block
            'BlockHeroConfig.php',
            'BlockHeroContent.php',
            // the inline `image` object of the hero content
            'BlockHeroContentImage.php',
            'BlockSlotValue.php',
            // the component map
            'Blocks.php',
            'EntityBase.php',
            'EntityPerson.php',
            'EntityPersonAddress.php',
            'HeroItem.php',
            // a schema named `object` can not be a class name
            'ObjectType.php',
        ], array_keys($this->generate()));
    }

    public function testEveryFileIsValidPhpAndMarkedAsGenerated()
    {
        $directory = sys_get_temp_dir() . '/flyo-types-' . getmypid();
        is_dir($directory) || mkdir($directory, 0777, true);

        foreach ($this->generate() as $name => $code) {
            $file = $directory . '/' . $name;
            file_put_contents($file, $code);

            $this->assertStringContainsString(TypeGenerator::MARKER, $code);
            $this->assertSame(0, $this->lint($file), "The generated file {$name} is not valid php.");

            unlink($file);
        }

        rmdir($directory);
    }

    public function testShapePropertiesDescribeTheJsonKeys()
    {
        $code = $this->generate()['BlockHeroContent.php'];

        $this->assertStringContainsString('namespace app\models\flyo;', $code);
        $this->assertStringContainsString('class BlockHeroContent extends Shape', $code);
        $this->assertStringContainsString('use Flyo\Yii\Types\Shape;', $code);

        // the description of the schema and of the property end up in the doc block
        $this->assertStringContainsString('The content of the hero block.', $code);
        $this->assertStringContainsString('The headline of the hero.', $code);

        // scalars, every property is nullable because a key can be missing in the response
        $this->assertStringContainsString('public ?string $headline = null;', $code);
        $this->assertStringContainsString('public ?int $count = null;', $code);
        $this->assertStringContainsString('public ?float $ratio = null;', $code);
        $this->assertStringContainsString('public ?bool $active = null;', $code);
        $this->assertStringContainsString('public ?string $nullable_string = null;', $code);

        // an inline object becomes its own shape
        $this->assertStringContainsString('public ?BlockHeroContentImage $image = null;', $code);

        // lists, maps and unions are described in the doc block
        $this->assertStringContainsString('@var array<int, string>|null', $code);
        $this->assertStringContainsString('public ?array $tags = null;', $code);
        $this->assertStringContainsString('@var array<string, string>|null', $code);
        $this->assertStringContainsString('public ?array $links = null;', $code);
        $this->assertStringContainsString('@var string|int|null', $code);
        $this->assertStringContainsString('public $either = null;', $code);

        // an object without properties stays untyped
        $this->assertStringContainsString('public ?object $meta = null;', $code);
        $this->assertStringContainsString('@var mixed', $code);
        $this->assertStringContainsString('public $untyped = null;', $code);

        // keys which are no valid php property names are documented instead of declared
        $this->assertStringNotContainsString('$my-key', $code);
        $this->assertStringContainsString("read\n * them with `\$shape->{'my-key'}`.", $code);
    }

    public function testEnumValuesBecomeConstants()
    {
        $code = $this->generate()['BlockHeroConfig.php'];

        $this->assertStringContainsString("public const VARIANT_LIGHT = 'light';", $code);
        $this->assertStringContainsString("public const VARIANT_DARK = 'dark';", $code);
        $this->assertStringContainsString('public ?string $variant = null;', $code);
    }

    public function testABlockSchemaBecomesAnAccessorForItsUntypedJson()
    {
        $code = $this->generate()['BlockHero.php'];

        $this->assertStringContainsString('final class BlockHero', $code);
        $this->assertStringContainsString("public const COMPONENT = 'Hero';", $code);

        $this->assertStringContainsString('public static function content(object $block): mixed', $code);
        $this->assertStringContainsString('return BlockHeroContent::ofContent($block);', $code);
        $this->assertStringContainsString('@return BlockHeroContent', $code);

        $this->assertStringContainsString('public static function config(object $block): mixed', $code);
        $this->assertStringContainsString('return BlockHeroConfig::ofConfig($block);', $code);

        $this->assertStringContainsString('public static function items(object $block): array', $code);
        $this->assertStringContainsString('return HeroItem::ofItems($block);', $code);
        $this->assertStringContainsString('@return HeroItem[]', $code);

        $this->assertStringContainsString('public static function slots(object $block): array', $code);
        $this->assertStringContainsString('return Accessor::slots($block);', $code);
        $this->assertStringContainsString('use Flyo\Yii\Types\Accessor;', $code);

        // a block accessor is not a shape, the block itself is still the model of the sdk
        $this->assertStringNotContainsString('extends Shape', $code);
    }

    public function testTheComponentMapContainsEveryBlock()
    {
        $code = $this->generate()['Blocks.php'];

        $this->assertStringContainsString("'Hero' => BlockHero::class,", $code);
        $this->assertStringContainsString("'HTML' => BlockHTML::class,", $code);
        $this->assertStringNotContainsString('HeroItem::class', $code);
    }

    public function testAllOfSchemasAreMerged()
    {
        $code = $this->generate()['EntityPerson.php'];

        $this->assertStringContainsString('public ?int $id = null;', $code);
        $this->assertStringContainsString('public ?string $firstname = null;', $code);
        $this->assertStringContainsString('public ?EntityPersonAddress $address = null;', $code);
    }

    public function testTheComponentsAndClassNamesAreExposed()
    {
        $generator = new TypeGenerator($this->document(), ['namespace' => 'app\\models\\flyo']);
        $generator->generate();

        $this->assertSame(['HTML' => 'BlockHTML', 'Hero' => 'BlockHero'], $generator->getComponents());
        $this->assertSame('BlockHeroContent', $generator->getClassNames()['blockHeroContent']);
    }

    public function testTheMapClassCanBeDisabled()
    {
        $files = (new TypeGenerator($this->document(), ['namespace' => 'app\\models\\flyo', 'mapClass' => '']))->generate();

        $this->assertArrayNotHasKey('Blocks.php', $files);
    }

    public function testTheGenerationIsDeterministic()
    {
        $first = (new TypeGenerator($this->document(), ['namespace' => 'app\\models\\flyo']))->generate();
        $second = (new TypeGenerator($this->document(), ['namespace' => 'app\\models\\flyo']))->generate();

        $this->assertSame($first, $second);
    }

    public function testTheNamespaceIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);

        new TypeGenerator($this->document(), ['namespace' => '']);
    }

    public function testADocumentWithoutSchemas()
    {
        $this->expectException(InvalidArgumentException::class);

        SchemaDocument::fromJson('{"openapi": "3.0.3"}');
    }

    public function testSchemasAreFoundInEveryDocumentLayout()
    {
        $schema = ['blockHero' => ['type' => 'object', 'properties' => ['component' => ['type' => 'string']]]];

        foreach ([['components' => ['schemas' => $schema]], ['schemas' => $schema], ['definitions' => $schema], $schema] as $document) {
            $this->assertSame($schema, (new SchemaDocument($document))->getSchemas());
        }
    }

    private function lint(string $file): int
    {
        exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $output, $code);

        return $code;
    }
}
