<?php

namespace Flyo\Yii\Types;

use InvalidArgumentException;

/**
 * Generates the php type specs of your blocks and entities from the openapi schemas of your flyo project.
 *
 * The generator emits two kinds of classes:
 *
 * 1. a **shape** for every schema which describes untyped json (the content, the config and the items of a
 *    block or the detail data of an entity). A shape declares one public property per json key and extends
 *    [[Shape]], it describes the `stdClass` object of the api response instead of replacing it, therefore
 *    it has no runtime cost at all, see [[Shape]].
 * 2. an **accessor** for every schema which describes a whole block (a schema with a `component` property).
 *    The accessor has one static method per untyped part of the block, so a view resolves its typed content
 *    with a single line: `$content = BlockHero::content($block);`
 *
 * The generated code only depends on this module, not on the openapi generator, and it never changes what
 * the widgets pass to your views.
 */
class TypeGenerator
{
    /**
     * @var string Marks a file as generated, used by the console command to detect (and remove) files of a
     * previous run.
     */
    public const MARKER = '@flyo-generated';

    /**
     * @var string[] Class names which can not be used because they are reserved by php.
     */
    private const RESERVED_NAMES = [
        'array', 'bool', 'callable', 'enum', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'null',
        'numeric', 'object', 'parent', 'resource', 'self', 'static', 'string', 'true', 'void',
    ];

    private SchemaDocument $document;

    private string $namespace;

    private string $baseClass;

    private string $mapClass;

    /**
     * @var array<string, string> Schema name to class name.
     */
    private array $classNames = [];

    /**
     * @var array<string, bool> Lower cased class names which are taken already.
     */
    private array $usedNames = [];

    /**
     * @var array<string, array{name: string, title: string, schema: array<string, mixed>}> Inline schemas
     * which need their own class, keyed by the class name.
     */
    private array $queue = [];

    /**
     * @var array<string, string> Context (parent class and json key) to class name of inline schemas.
     */
    private array $inlineClasses = [];

    /**
     * @var string The name of the schema which is rendered at the moment, used to describe inline objects.
     */
    private string $currentSchemaName = '';

    /**
     * @var array<string, string> Component name to accessor class name.
     */
    private array $components = [];

    /**
     * @var array<string, string> Class name to php code.
     */
    private array $files = [];

    /**
     * @param SchemaDocument $document The openapi document with the schemas.
     * @param array{namespace: string, baseClass?: string, mapClass?: string} $options
     */
    public function __construct(SchemaDocument $document, array $options)
    {
        $namespace = trim($options['namespace'] ?? '', '\\');

        if ($namespace === '') {
            throw new InvalidArgumentException('The `namespace` option is required to generate the type specs.');
        }

        $this->document = $document;
        $this->namespace = $namespace;
        $this->baseClass = trim($options['baseClass'] ?? Shape::class, '\\');
        $this->mapClass = trim($options['mapClass'] ?? 'Blocks', '\\');
    }

    /**
     * Generates the code of all type specs.
     *
     * @return array<string, string> File name (relative to the output folder) to php code.
     */
    public function generate(): array
    {
        $this->classNames = [];
        $this->usedNames = [];
        $this->queue = [];
        $this->inlineClasses = [];
        $this->components = [];
        $this->files = [];

        foreach ([$this->baseClassName(), $this->mapClass] as $reserved) {
            if ($reserved !== '') {
                $this->usedNames[strtolower($reserved)] = true;
            }
        }

        $schemas = $this->document->getSchemas();
        ksort($schemas);

        foreach (array_keys($schemas) as $name) {
            $this->classNames[$name] = $this->uniqueClassName(self::classify($name));
        }

        foreach ($schemas as $name => $schema) {
            $class = $this->classNames[$name];

            $this->currentSchemaName = $name;

            $this->files[$class] = $this->isBlockSchema($schema)
                ? $this->renderBlockAccessor($class, $name, $schema)
                : $this->renderShape($class, "Type spec of the openapi schema `{$name}`.", $schema);
        }

        // rendering a class can discover inline objects which need their own class, those can again contain
        // inline objects, therefore the queue is processed until it is empty.
        while ($this->queue !== []) {
            $queued = $this->queue;
            $this->queue = [];

            foreach ($queued as $class => $inline) {
                $this->currentSchemaName = $inline['name'];
                $this->files[$class] = $this->renderShape($class, $inline['title'], $inline['schema']);
            }
        }

        if ($this->mapClass !== '' && $this->components !== []) {
            $this->files[$this->mapClass] = $this->renderComponentMap();
        }

        ksort($this->files);

        $files = [];

        foreach ($this->files as $class => $code) {
            $files[$class . '.php'] = $code;
        }

        return $files;
    }

    /**
     * The class names of all generated type specs, keyed by their schema name.
     *
     * @return array<string, string>
     */
    public function getClassNames(): array
    {
        return $this->classNames;
    }

    /**
     * The accessor class of every block component.
     *
     * @return array<string, string>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Whether the given schema describes a whole block (and not the untyped json inside of a block).
     *
     * @param array<string, mixed> $schema
     */
    private function isBlockSchema(array $schema): bool
    {
        $properties = $this->propertiesOf($schema);

        if (!isset($properties['component'])) {
            return false;
        }

        foreach (['uid', 'content', 'config', 'items', 'slots'] as $key) {
            if (isset($properties[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The properties of a schema, including the properties of its `allOf` parts.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function propertiesOf(array $schema, int $depth = 0): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        if ($depth > 5 || !is_array($schema['allOf'] ?? null)) {
            return $properties;
        }

        foreach ($schema['allOf'] as $part) {
            if (!is_array($part)) {
                continue;
            }

            if (isset($part['$ref']) && is_string($part['$ref'])) {
                $resolved = $this->document->getSchema(self::refName($part['$ref']));

                if ($resolved !== null) {
                    // properties of the schema itself win over the inherited ones
                    $properties += $this->propertiesOf($resolved, $depth + 1);
                }

                continue;
            }

            $properties += $this->propertiesOf($part, $depth + 1);
        }

        return $properties;
    }

    /**
     * Resolves the php type of a single json schema.
     *
     * @param array<string, mixed> $schema
     * @param string $context The class name an inline object of this schema would get.
     */
    private function resolveType(array $schema, string $context): TypeSpec
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $class = $this->classNames[self::refName($schema['$ref'])] ?? null;

            return $class === null ? TypeSpec::mixed() : TypeSpec::shape($class);
        }

        if (is_array($schema['allOf'] ?? null)) {
            return $this->resolveAllOf($schema, $context);
        }

        $union = $schema['oneOf'] ?? $schema['anyOf'] ?? null;

        if (is_array($union)) {
            $types = [];

            foreach ($union as $part) {
                if (is_array($part) && ($part['type'] ?? null) !== 'null') {
                    $types[] = $this->resolveType($part, $context);
                }
            }

            return TypeSpec::union($types);
        }

        $type = $schema['type'] ?? null;

        if (is_array($type)) {
            $types = array_values(array_filter($type, static fn (mixed $entry): bool => is_string($entry) && $entry !== 'null'));

            if (count($types) > 1) {
                return TypeSpec::union(array_map(fn (string $entry): TypeSpec => $this->resolveType(['type' => $entry] + $schema, $context), $types));
            }

            $type = $types[0] ?? null;
        }

        return match ($type) {
            'string' => TypeSpec::scalar('string'),
            'integer' => TypeSpec::scalar('int'),
            'number' => TypeSpec::scalar('float'),
            'boolean' => TypeSpec::scalar('bool'),
            'array' => TypeSpec::listOf(is_array($schema['items'] ?? null) ? $this->resolveType($schema['items'], $context . 'Item') : TypeSpec::mixed()),
            'object', null => $this->resolveObject($schema, $context, $type === 'object'),
            default => TypeSpec::mixed(),
        };
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function resolveAllOf(array $schema, string $context): TypeSpec
    {
        $parts = is_array($schema['allOf']) ? $schema['allOf'] : [];

        if (count($parts) === 1 && is_array($parts[array_key_first($parts)])) {
            return $this->resolveType($parts[array_key_first($parts)], $context);
        }

        $properties = $this->propertiesOf($schema);

        if ($properties === []) {
            return TypeSpec::plainObject();
        }

        return TypeSpec::shape($this->enqueueInline($context, [
            'type' => 'object',
            'properties' => $properties,
        ] + array_intersect_key($schema, ['description' => true, 'title' => true])));
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function resolveObject(array $schema, string $context, bool $isObject): TypeSpec
    {
        if (!empty($schema['properties']) && is_array($schema['properties'])) {
            return TypeSpec::shape($this->enqueueInline($context, $schema));
        }

        if (is_array($schema['additionalProperties'] ?? null)) {
            return TypeSpec::mapOf($this->resolveType($schema['additionalProperties'], $context . 'Value'));
        }

        return $isObject ? TypeSpec::plainObject() : TypeSpec::mixed();
    }

    /**
     * Registers an inline schema which needs its own class and returns the class name.
     *
     * @param array<string, mixed> $schema
     */
    private function enqueueInline(string $context, array $schema): string
    {
        if (isset($this->inlineClasses[$context])) {
            return $this->inlineClasses[$context];
        }

        $class = $this->uniqueClassName(self::classify($context));

        $this->inlineClasses[$context] = $class;
        $this->queue[$class] = [
            'name' => $this->currentSchemaName,
            'title' => "Type spec of a nested object of the openapi schema `{$this->currentSchemaName}`.",
            'schema' => $schema,
        ];

        return $class;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function renderShape(string $class, string $title, array $schema): string
    {
        $properties = $this->propertiesOf($schema);
        $skipped = [];
        $constants = [];
        $members = [];

        foreach ($properties as $key => $property) {
            $key = (string) $key;

            if (!is_array($property)) {
                continue;
            }

            if (!self::isValidPropertyName($key)) {
                $skipped[] = $key;
                continue;
            }

            $spec = $this->resolveType($property, $class . self::classify($key));
            $constants = array_merge($constants, $this->renderEnumConstants($key, $property));
            $members = array_merge($members, $this->renderProperty($key, $property, $spec));
        }

        $members = array_merge($constants, $members);

        $doc = [$title];
        $description = self::description($schema);

        if ($description !== null) {
            $doc[] = '';
            $doc[] = $description;
        }

        $doc[] = '';
        $doc[] = 'Every property is nullable because it describes json: a key which is missing in the api';
        $doc[] = 'response is null and not an error, see [[Shape]].';

        if ($skipped !== []) {
            $doc[] = '';
            $doc[] = 'The following keys are not valid php property names and are therefore not declared, read';
            $doc[] = "them with `\$shape->{'" . implode("'}`, `\$shape->{'", $skipped) . "'}`.";
        }

        $extends = $this->baseClass === '' ? '' : ' extends ' . $this->baseClassName();

        return $this->renderFile(
            $this->baseClass === '' ? [] : [$this->baseClass],
            $doc,
            'class ' . $class . $extends,
            $members
        );
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function renderBlockAccessor(string $class, string $schemaName, array $schema): string
    {
        $this->currentSchemaName = $schemaName;
        $properties = $this->propertiesOf($schema);
        $component = $this->componentOf($class, $properties);
        $imports = [];
        $members = [];

        $members[] = '/**';
        $members[] = ' * @var string The name of the block component, its view file is `views/flyo/' . $component . '.php`.';
        $members[] = ' */';
        $members[] = "public const COMPONENT = '" . self::escape($component) . "';";

        foreach (['content' => 'ofContent', 'config' => 'ofConfig'] as $key => $factory) {
            if (!is_array($properties[$key] ?? null)) {
                continue;
            }

            $spec = $this->resolveType($properties[$key], $class . self::classify($key));

            if ($spec->class === null) {
                continue;
            }

            $members[] = '';
            $members[] = '/**';
            $members[] = ' * The typed ' . $key . ' of the block, the object of the api response is not modified.';
            $members[] = ' *';
            $members[] = ' * @param object $block Any block representation, see [[\\' . Accessor::class . '::read()]].';
            $members[] = ' * @return ' . $spec->class;
            $members[] = ' */';
            $members[] = 'public static function ' . $key . '(object $block): mixed';
            $members[] = '{';
            $members[] = '    return ' . $spec->class . '::' . $factory . '($block);';
            $members[] = '}';
        }

        if (is_array($properties['items'] ?? null)) {
            // the context of the list is the block itself, so a single entry becomes `<Block>Item`
            $spec = $this->resolveType($properties['items'], $class);
            $itemClass = $spec->item?->class;

            if ($itemClass !== null) {
                $members[] = '';
                $members[] = '/**';
                $members[] = ' * The typed items of the block.';
                $members[] = ' *';
                $members[] = ' * @param object $block Any block representation, see [[\\' . Accessor::class . '::read()]].';
                $members[] = ' * @return ' . $itemClass . '[]';
                $members[] = ' */';
                $members[] = 'public static function items(object $block): array';
                $members[] = '{';
                $members[] = '    return ' . $itemClass . '::ofItems($block);';
                $members[] = '}';
            }
        }

        if (isset($properties['slots'])) {
            $imports[] = Accessor::class;

            $members[] = '';
            $members[] = '/**';
            $members[] = ' * The slots of the block, keyed by the slot identifier. Render the blocks of a slot with';
            $members[] = ' * [[\\Flyo\\Yii\\Widgets\\SlotRenderWidget]].';
            $members[] = ' *';
            $members[] = ' * @param object $block Any block representation, see [[\\' . Accessor::class . '::read()]].';
            $members[] = ' * @return array<string, \\Flyo\\Model\\BlockSlotValue>';
            $members[] = ' */';
            $members[] = 'public static function slots(object $block): array';
            $members[] = '{';
            $members[] = '    return Accessor::slots($block);';
            $members[] = '}';
        }

        $this->components[$component] = $class;

        $doc = ["Type spec of the `{$component}` block, generated from the openapi schema `{$schemaName}`."];
        $description = self::description($schema);

        if ($description !== null) {
            $doc[] = '';
            $doc[] = $description;
        }

        $doc[] = '';
        $doc[] = 'The block itself is still the model of the flyo php sdk, only its untyped json is described:';
        $doc[] = '';
        $doc[] = '```php';
        $doc[] = '// views/flyo/' . $component . '.php';
        $doc[] = '$content = ' . $class . '::content($block);';
        $doc[] = '```';

        return $this->renderFile($imports, $doc, 'final class ' . $class, $members);
    }

    private function renderComponentMap(): string
    {
        $components = $this->components;
        ksort($components);

        $members = [
            '/**',
            ' * @var array<string, class-string> The type spec class of every block component.',
            ' */',
            'public const COMPONENTS = [',
        ];

        foreach ($components as $component => $class) {
            $members[] = "    '" . self::escape($component) . "' => " . $class . '::class,';
        }

        $members[] = '];';

        return $this->renderFile([], [
            'All block components of your flyo project and the type spec class which describes them.',
            '',
            'Useful to find out whether a block has a type spec at all:',
            '',
            '```php',
            '$class = ' . $this->mapClass . '::COMPONENTS[$block->getComponent()] ?? null;',
            '```',
        ], 'final class ' . $this->mapClass, $members);
    }

    /**
     * @param string[] $imports
     * @param string[] $doc
     * @param string[] $members
     */
    private function renderFile(array $imports, array $doc, string $declaration, array $members): string
    {
        $lines = [
            '<?php',
            '',
            '/*',
            ' * ' . self::MARKER,
            ' *',
            ' * This file is generated from the openapi schemas of your flyo project, do not edit it manually,',
            ' * your changes are lost with the next run of `yii flyo/types/generate`.',
            ' */',
            '',
            'namespace ' . $this->namespace . ';',
        ];

        $imports = array_unique($imports);
        sort($imports);

        if ($imports !== []) {
            $lines[] = '';

            foreach ($imports as $import) {
                $lines[] = 'use ' . $import . ';';
            }
        }

        $lines[] = '';
        $lines[] = '/**';

        foreach ($doc as $line) {
            $lines[] = rtrim(' * ' . $line);
        }

        $lines[] = ' */';
        $lines[] = $declaration;
        $lines[] = '{';

        foreach (self::normalize($members) as $member) {
            $lines[] = rtrim('    ' . $member);
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, mixed> $schema
     * @return string[]
     */
    private function renderProperty(string $key, array $schema, TypeSpec $spec): array
    {
        $doc = [];
        $description = self::description($schema);

        if ($description !== null) {
            $doc[] = ' * ' . $description;
        }

        if ($spec->needsDocType()) {
            if ($doc !== []) {
                $doc[] = ' *';
            }

            $doc[] = ' * @var ' . $spec->getDocType();
        }

        $lines = [''];

        if ($doc !== []) {
            $lines[] = '/**';
            $lines = array_merge($lines, $doc);
            $lines[] = ' */';
        }

        $lines[] = trim('public ' . ($spec->native ?? '')) . ' $' . $key . ' = null;';

        return $lines;
    }

    /**
     * Emits a constant for every value of an enum, so a comparison in a view is not a magic string.
     *
     * @param array<string, mixed> $schema
     * @return string[]
     */
    private function renderEnumConstants(string $key, array $schema): array
    {
        if (!is_array($schema['enum'] ?? null)) {
            return [];
        }

        $lines = [];
        $used = [];

        foreach ($schema['enum'] as $value) {
            if (!is_string($value) && !is_int($value)) {
                continue;
            }

            $name = strtoupper(self::snake($key) . '_' . self::snake((string) $value));
            $name = trim((string) preg_replace('/[^A-Z0-9]+/', '_', $name), '_');

            if ($name === '' || ctype_digit($name[0]) || isset($used[$name])) {
                continue;
            }

            $used[$name] = true;
            $lines[] = '';
            $lines[] = 'public const ' . $name . ' = ' . (is_int($value) ? $value : "'" . self::escape($value) . "'") . ';';
        }

        return $lines;
    }

    /**
     * The component name of a block schema, either from the `component` property of the schema or, as a
     * fallback, from the schema name.
     *
     * @param array<string, mixed> $properties
     */
    private function componentOf(string $class, array $properties): string
    {
        $schema = is_array($properties['component'] ?? null) ? $properties['component'] : [];

        foreach ([$schema['const'] ?? null, $schema['default'] ?? null, is_array($schema['enum'] ?? null) && count($schema['enum']) === 1 ? reset($schema['enum']) : null] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return (string) preg_replace('/^Block/', '', $class);
    }

    /**
     * Removes empty lines at the beginning and at the end of a class body and collapses multiple empty
     * lines into a single one.
     *
     * @param string[] $lines
     * @return string[]
     */
    private static function normalize(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $isEmpty = trim($line) === '';

            if ($isEmpty && ($normalized === [] || trim((string) end($normalized)) === '')) {
                continue;
            }

            $normalized[] = $line;
        }

        while ($normalized !== [] && trim((string) end($normalized)) === '') {
            array_pop($normalized);
        }

        return $normalized;
    }

    private function baseClassName(): string
    {
        $parts = explode('\\', $this->baseClass);

        return (string) end($parts);
    }

    private function uniqueClassName(string $class): string
    {
        if (in_array(strtolower($class), self::RESERVED_NAMES, true)) {
            $class .= 'Type';
        }

        $unique = $class;
        $suffix = 2;

        while (isset($this->usedNames[strtolower($unique)])) {
            $unique = $class . $suffix++;
        }

        $this->usedNames[strtolower($unique)] = true;

        return $unique;
    }

    /**
     * The name of a schema from a json pointer like `#/components/schemas/blockHero`.
     */
    private static function refName(string $ref): string
    {
        $parts = explode('/', $ref);

        return (string) end($parts);
    }

    /**
     * Turns a schema name or a json key into a class name, existing camel case is kept: `blockHTML` becomes
     * `BlockHTML` and `block_hero-content` becomes `BlockHeroContent`.
     */
    private static function classify(string $name): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $class = implode('', array_map(static fn (string $part): string => ucfirst($part), $parts));

        if ($class === '') {
            return 'Schema';
        }

        return ctype_digit($class[0]) ? 'Schema' . $class : $class;
    }

    private static function snake(string $value): string
    {
        return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value));
    }

    private static function isValidPropertyName(string $key): bool
    {
        return preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $key) === 1 && strtolower($key) !== 'this';
    }

    /**
     * @param array<string, mixed> $schema
     */
    private static function description(array $schema): ?string
    {
        foreach (['description', 'title'] as $key) {
            $value = $schema[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return str_replace(['*/', "\r", "\n"], ['*\\/', '', ' '], trim($value));
            }
        }

        return null;
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
