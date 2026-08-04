<?php

namespace Flyo\Yii\Types;

use ArrayAccess;

/**
 * Reads values from the nitro json objects without knowing their concrete class.
 *
 * The module must work with three different representations of the very same json:
 *
 * 1. the models of the flyo php sdk (`Flyo\Model\Block`), where values are read through getters,
 * 2. your own generated models (see [[\Flyo\Yii\Module::$blockModels]]), which live in your namespace
 *    and can not extend the sdk models, but also provide getters,
 * 3. the raw `stdClass` objects of the untyped parts of the json (`content`, `config`, `items`, `model`).
 *
 * Instead of type hinting one of them, the widgets and actions of this module read the values through
 * this accessor, therefore they work with all three representations, see [[read()]].
 */
class Accessor
{
    /**
     * Reads a single value from the given object.
     *
     * The value is looked up in the following order, the first hit wins:
     *
     * 1. the getter method of the key (`meta_json` reads `getMetaJson()`),
     * 2. a public property with the exact name of the key (this is the `stdClass` case),
     * 3. the array access of the object (the sdk models implement `ArrayAccess`).
     *
     * @param object $object The object to read from.
     * @param string $key The json key, snake case keys are converted to their camel case getter.
     * @param mixed $default Returned when the key does not exist or its value is null.
     * @return mixed
     */
    public static function read(object $object, string $key, mixed $default = null): mixed
    {
        $getter = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-', '.'], ' ', $key)));

        if (method_exists($object, $getter) && is_callable([$object, $getter])) {
            return $object->$getter() ?? $default;
        }

        // from outside of the class scope this only returns the public properties, therefore private
        // properties of the sdk models are not exposed by accident.
        $properties = get_object_vars($object);

        if (array_key_exists($key, $properties)) {
            return $properties[$key] ?? $default;
        }

        if ($object instanceof ArrayAccess && $object->offsetExists($key)) {
            return $object->offsetGet($key) ?? $default;
        }

        return $default;
    }

    /**
     * The uid of a block, used to make a block editable, see [[\Flyo\Yii\Widgets\Editable]].
     */
    public static function uid(object $block): string
    {
        return (string) self::read($block, 'uid', '');
    }

    /**
     * The component name of a block, this is the name of the view file which renders the block.
     */
    public static function component(object $block): string
    {
        return (string) self::read($block, 'component', '');
    }

    /**
     * The identifier of a block or a slot.
     */
    public static function identifier(object $object): string
    {
        return (string) self::read($object, 'identifier', '');
    }

    /**
     * The untyped content of a block, usually a `stdClass` object.
     */
    public static function content(object $block): mixed
    {
        return self::read($block, 'content');
    }

    /**
     * The untyped config of a block, usually a `stdClass` object.
     */
    public static function config(object $block): mixed
    {
        return self::read($block, 'config');
    }

    /**
     * The untyped items of a block, usually a list of `stdClass` objects.
     *
     * @return array<int|string, mixed>
     */
    public static function items(object $block): array
    {
        return self::toArray(self::read($block, 'items'));
    }

    /**
     * The slots of a block, keyed by the slot identifier.
     *
     * @return array<string, mixed>
     */
    public static function slots(object $block): array
    {
        return self::toArray(self::read($block, 'slots'));
    }

    /**
     * A single slot of a block or null when the block has no slot with the given identifier.
     */
    public static function slot(object $block, string $identifier): mixed
    {
        return self::slots($block)[$identifier] ?? null;
    }

    /**
     * The blocks of a page (`json`) or of a slot (`content`).
     *
     * @return array<int|string, object>
     */
    public static function blocks(object $pageOrSlot): array
    {
        $blocks = self::read($pageOrSlot, 'json');

        if ($blocks === null) {
            $blocks = self::read($pageOrSlot, 'content');
        }

        return array_filter(self::toArray($blocks), 'is_object');
    }

    /**
     * The untyped detail data of an entity, usually a `stdClass` object.
     */
    public static function model(object $entity): mixed
    {
        return self::read($entity, 'model');
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return [];
    }
}
