<?php

namespace Flyo\Yii\Types;

/**
 * The php type of a single json schema, used by the [[TypeGenerator]].
 */
final class TypeSpec
{
    /**
     * @param string|null $native The native property type of the generated class, null when the type can
     * only be expressed in a docblock (unions and mixed).
     * @param string $bare The phpstan type without the null part, e.g. `string`, `BlockHeroContent` or
     * `array<int, BlockHeroItem>`.
     * @param string|null $class The generated class of the type, if there is one.
     * @param self|null $item The type of the list entries, if the type is a list.
     */
    private function __construct(
        public readonly ?string $native,
        public readonly string $bare,
        public readonly ?string $class = null,
        public readonly ?self $item = null,
    ) {
    }

    public static function mixed(): self
    {
        return new self(null, 'mixed');
    }

    /**
     * @param string $type One of `string`, `int`, `float` or `bool`.
     */
    public static function scalar(string $type): self
    {
        return new self('?' . $type, $type);
    }

    /**
     * An object without any known property, the untyped `stdClass` of the api response.
     */
    public static function plainObject(): self
    {
        return new self('?object', 'object');
    }

    /**
     * A generated shape class.
     */
    public static function shape(string $class): self
    {
        return new self('?' . $class, $class, $class);
    }

    public static function listOf(self $item): self
    {
        return new self('?array', 'array<int, ' . $item->bare . '>', null, $item);
    }

    public static function mapOf(self $value): self
    {
        return new self('?array', 'array<string, ' . $value->bare . '>', null, $value);
    }

    /**
     * @param self[] $types
     */
    public static function union(array $types): self
    {
        $bare = [];

        foreach ($types as $type) {
            $bare[$type->bare] = $type->bare;
        }

        if ($bare === []) {
            return self::mixed();
        }

        if (count($bare) === 1) {
            return reset($types);
        }

        return new self(null, implode('|', $bare));
    }

    /**
     * The type for the `@var` annotation, every value of the untyped json can be missing and is therefore
     * nullable.
     */
    public function getDocType(): string
    {
        return $this->bare === 'mixed' ? 'mixed' : $this->bare . '|null';
    }

    /**
     * Whether the native type is precise enough or the property needs an additional `@var` annotation.
     */
    public function needsDocType(): bool
    {
        return $this->native === null || str_starts_with($this->bare, 'array<');
    }
}
