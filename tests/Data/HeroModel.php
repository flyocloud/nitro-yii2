<?php

namespace Flyo\Yii\Tests\Data;

/**
 * Behaves like a block model which has been generated with the openapi generator into the namespace of an
 * application, see [[\Flyo\Yii\Module::$blockModels]]. It does not extend `Flyo\Model\Block` on purpose,
 * generated models can not extend a class of the sdk.
 */
class HeroModel
{
    public const DISCRIMINATOR = null;

    private ?string $uid = null;

    private ?string $component = null;

    private mixed $content = null;

    /**
     * @return array<string, string>
     */
    public static function openAPITypes(): array
    {
        return ['uid' => 'string', 'component' => 'string', 'content' => 'object'];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeMap(): array
    {
        return ['uid' => 'uid', 'component' => 'component', 'content' => 'content'];
    }

    /**
     * @return array<string, string>
     */
    public static function setters(): array
    {
        return ['uid' => 'setUid', 'component' => 'setComponent', 'content' => 'setContent'];
    }

    /**
     * @return array<string, string>
     */
    public static function getters(): array
    {
        return ['uid' => 'getUid', 'component' => 'getComponent', 'content' => 'getContent'];
    }

    public static function isNullable(string $property): bool
    {
        return false;
    }

    public function setUid(?string $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setComponent(?string $component): self
    {
        $this->component = $component;

        return $this;
    }

    public function getComponent(): ?string
    {
        return $this->component;
    }

    public function setContent(mixed $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }
}
