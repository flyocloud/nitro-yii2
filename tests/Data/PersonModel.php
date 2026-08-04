<?php

namespace Flyo\Yii\Tests\Data;

/**
 * Behaves like a model for the detail data of an entity which has been generated with the openapi generator,
 * see [[\Flyo\Yii\Module::$entityModels]].
 */
class PersonModel
{
    public const DISCRIMINATOR = null;

    private ?string $firstname = null;

    private ?int $age = null;

    /**
     * @return array<string, string>
     */
    public static function openAPITypes(): array
    {
        return ['firstname' => 'string', 'age' => 'int'];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeMap(): array
    {
        return ['firstname' => 'firstname', 'age' => 'age'];
    }

    /**
     * @return array<string, string>
     */
    public static function setters(): array
    {
        return ['firstname' => 'setFirstname', 'age' => 'setAge'];
    }

    /**
     * @return array<string, string>
     */
    public static function getters(): array
    {
        return ['firstname' => 'getFirstname', 'age' => 'getAge'];
    }

    public static function isNullable(string $property): bool
    {
        return false;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }
}
