<?php

namespace Flyo\Yii\Tests;

use Flyo\ApiException;
use Flyo\Yii\LiveEdit;
use Flyo\Yii\Module;
use yii\base\Event;
use yii\web\Application;
use yii\web\View;

class LiveEditTest extends BaseTestCase
{
    private int $webApplications = 0;

    protected function tearDown(): void
    {
        Event::off(View::class, View::EVENT_BEGIN_PAGE);

        // every application registers its own error/exception handlers, restore the ones from the web applications
        // created in this test, the handlers of the console application are restored by the parent.
        while ($this->webApplications > 0) {
            restore_error_handler();
            restore_exception_handler();
            $this->webApplications--;
        }

        parent::tearDown();
    }

    public function testRegisterBridgeAndBootScript()
    {
        $this->createWebApplication();

        $view = new View();
        LiveEdit::register($view);

        $this->assertSame([
            View::POS_END => [
                'flyo-bridge-cdn' => '<script src="' . Module::LIVE_EDIT_BRIDGE_URL . '"></script>',
            ],
        ], $view->jsFiles);

        $this->assertSame(['flyo-live-edit-boot'], array_keys($view->js[View::POS_END]));
        $this->assertStringContainsString('bridge.reload();', $view->js[View::POS_END]['flyo-live-edit-boot']);
        $this->assertStringContainsString('bridge.scrollTo();', $view->js[View::POS_END]['flyo-live-edit-boot']);
        $this->assertStringContainsString('[data-flyo-uid]', $view->js[View::POS_END]['flyo-live-edit-boot']);
    }

    public function testRegisterOnlyOnce()
    {
        $this->createWebApplication();

        $view = new View();
        LiveEdit::register($view);
        LiveEdit::register($view, 'https://example.com/bridge.js');

        $this->assertSame([
            View::POS_END => [
                'flyo-bridge-cdn' => '<script src="' . Module::LIVE_EDIT_BRIDGE_URL . '"></script>',
            ],
        ], $view->jsFiles);
    }

    public function testIsLiveEditEnabled()
    {
        // YII_ENV is `dev` while testing
        $this->assertTrue((new Module('flyo', null, ['token' => 'foobar']))->getIsLiveEditEnabled());
        $this->assertFalse((new Module('flyo', null, ['token' => 'foobar', 'liveEdit' => false]))->getIsLiveEditEnabled());
        $this->assertTrue((new Module('flyo', null, ['token' => 'foobar', 'liveEdit' => true]))->getIsLiveEditEnabled());
    }

    public function testWebApplicationRegistersLiveEditOnPageBegin()
    {
        $this->bootstrapModule($this->createWebApplication(), ['token' => 'foobar']);

        $view = new View();
        $view->trigger(View::EVENT_BEGIN_PAGE);

        $this->assertSame(['flyo-live-edit-boot'], array_keys($view->js[View::POS_END]));
        $this->assertSame([
            View::POS_END => [
                'flyo-bridge-cdn' => '<script src="' . Module::LIVE_EDIT_BRIDGE_URL . '"></script>',
            ],
        ], $view->jsFiles);
    }

    public function testWebApplicationWithCustomBridgeUrl()
    {
        $this->bootstrapModule($this->createWebApplication(), ['token' => 'foobar', 'liveEditBridgeUrl' => '/js/bridge.js']);

        $view = new View();
        $view->trigger(View::EVENT_BEGIN_PAGE);

        $this->assertSame([
            View::POS_END => [
                'flyo-bridge-cdn' => '<script src="/js/bridge.js"></script>',
            ],
        ], $view->jsFiles);
    }

    public function testWebApplicationWithDisabledLiveEditRegistersNothing()
    {
        $this->bootstrapModule($this->createWebApplication(), ['token' => 'foobar', 'liveEdit' => false]);

        $view = new View();
        $view->trigger(View::EVENT_BEGIN_PAGE);

        $this->assertSame([], $view->js);
        $this->assertSame([], $view->jsFiles);
    }

    public function testConsoleApplicationRegistersNothing()
    {
        $this->bootstrapModule($this->app, ['token' => 'foobar']);

        $view = new View();
        $view->trigger(View::EVENT_BEGIN_PAGE);

        $this->assertSame([], $view->js);
        $this->assertSame([], $view->jsFiles);
    }

    private function createWebApplication(): Application
    {
        $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $this->webApplications++;

        return new Application([
            'id' => 'test-web',
            'basePath' => __DIR__,
        ]);
    }

    /**
     * The bootstrap requests the nitro config api, which is not available while testing.
     */
    private function bootstrapModule(\yii\base\Application $app, array $config): void
    {
        try {
            (new Module('flyo', null, $config))->bootstrap($app);
        } catch (ApiException $exception) {
        }
    }
}
