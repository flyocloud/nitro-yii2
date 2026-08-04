<?php

namespace Flyo\Yii\Types;

use Flyo\Model\ModelInterface;
use InvalidArgumentException;
use LogicException;

/**
 * Base class of the generated type specs, see [[\Flyo\Yii\Controllers\TypesController]].
 *
 * A shape **describes** an untyped part of the nitro json (`content`, `config`, `items` of a block or
 * the `model` of an entity), it does not replace it. The generated subclass declares one public property
 * per json key, therefore your ide and phpstan know what a block content contains, while at runtime the
 * very same `stdClass` object of the api response is used:
 *
 * ```php
 * // views/flyo/Hero.php
 * /** @var \Flyo\Model\Block $block *\/
 * $content = BlockHeroContent::ofContent($block); // no serialization, no hydration, no copy
 *
 * echo $content->headline;                        // checked against the generated property
 * ```
 *
 * This is the php equivalent of the erased types of a typescript frontend: the type information is only
 * used by the tooling, the object stays exactly what the api returned. Therefore nothing has to be
 * migrated when a type spec is added to an existing view, and a block without a generated shape keeps
 * working, see [[of()]].
 */
abstract class Shape
{
    /**
     * The generated shapes are plain value declarations, they never need their own constructor. Keeping it
     * final makes [[of()]] safe to instantiate the concrete shape.
     */
    final public function __construct()
    {
    }

    /**
     * Reinterprets the given value as this shape.
     *
     * @param mixed $value The untyped value from the api response, usually a `stdClass` object. Arrays are
     * casted to an object, so json which has been decoded into an associative array is supported as well.
     * A value which is not an object (null, because the key is missing in the response) returns an empty
     * instance of the shape, therefore reading a property never fails.
     * @return static
     */
    public static function of(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = (object) $value;
        }

        if (is_object($value)) {
            if ($value instanceof ModelInterface) {
                throw new InvalidArgumentException(sprintf(
                    '%s::of() expects the untyped json of the api response, but got the typed model %s. Read the values of a typed model with its getters instead.',
                    static::class,
                    $value::class
                ));
            }

            /** @var static $value */
            return $value;
        }

        if (static::class === self::class) {
            throw new LogicException(sprintf('%s::of() must be called on a generated shape class.', self::class));
        }

        return new static();
    }

    /**
     * Reinterprets every entry of the given value as this shape, used for the items of a block.
     *
     * @param mixed $values A list of untyped values, anything else results in an empty array.
     * @return static[]
     */
    public static function ofList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_map(static fn (mixed $value): mixed => static::of($value), $values);
    }

    /**
     * Reinterprets the content of the given block as this shape.
     *
     * @param object $block Any block representation, see [[Accessor::read()]].
     * @return static
     */
    public static function ofContent(object $block): mixed
    {
        return static::of(Accessor::content($block));
    }

    /**
     * Reinterprets the config of the given block as this shape.
     *
     * @param object $block Any block representation, see [[Accessor::read()]].
     * @return static
     */
    public static function ofConfig(object $block): mixed
    {
        return static::of(Accessor::config($block));
    }

    /**
     * Reinterprets every item of the given block as this shape.
     *
     * @param object $block Any block representation, see [[Accessor::read()]].
     * @return static[]
     */
    public static function ofItems(object $block): array
    {
        return static::ofList(Accessor::items($block));
    }

    /**
     * Reinterprets the detail data (`model`) of the given entity as this shape.
     *
     * @param object $entity Usually a `Flyo\Model\Entity`.
     * @return static
     */
    public static function ofModel(object $entity): mixed
    {
        return static::of(Accessor::model($entity));
    }
}
