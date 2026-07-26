<?php

namespace Flyo\Yii\Tests;

use PHPUnit\Framework\TestCase;
use yii\caching\DummyCache;
use yii\console\Application;

class BaseTestCase extends TestCase
{
    public Application $app;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_error_handler();
        restore_exception_handler();
    }

    protected function setUp(): void
    {
        $this->app = new Application([
            'id' => 'test',
            'basePath' => __DIR__,
            'components' => [
                'cache' => [
                    'class' => DummyCache::class,
                ]
            ]
        ]);
    }
}
