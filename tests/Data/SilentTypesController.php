<?php

namespace Flyo\Yii\Tests\Data;

use Flyo\Yii\Controllers\TypesController;

/**
 * Keeps the output of the command in a property instead of writing it to the console.
 */
class SilentTypesController extends TypesController
{
    public string $output = '';

    public function stdout($string)
    {
        $this->output .= $string;

        return strlen($string);
    }

    public function stderr($string)
    {
        $this->output .= $string;

        return strlen($string);
    }
}
