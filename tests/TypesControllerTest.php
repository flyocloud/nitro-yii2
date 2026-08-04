<?php

namespace Flyo\Yii\Tests;

use Flyo\Yii\Module;
use Flyo\Yii\Tests\Data\SilentTypesController;
use Flyo\Yii\Types\TypeGenerator;
use Yii;
use yii\console\ExitCode;
use yii\helpers\FileHelper;

class TypesControllerTest extends BaseTestCase
{
    private string $path = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = Yii::getAlias('@runtime') . '/flyo-types';
        Yii::$app->controllerMap['flyo-types'] = SilentTypesController::class;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->path)) {
            FileHelper::removeDirectory($this->path);
        }

        Module::setInstance(null);

        parent::tearDown();
    }

    public function testGeneratesTheTypeSpecsIntoTheConfiguredFolder()
    {
        $this->setModuleInstance();

        $this->assertSame(ExitCode::OK, $this->generate());

        $files = array_map('basename', FileHelper::findFiles($this->path));
        sort($files);

        $this->assertContains('BlockHero.php', $files);
        $this->assertContains('BlockHeroContent.php', $files);
        $this->assertContains('Blocks.php', $files);

        $code = (string) file_get_contents($this->path . '/BlockHero.php');

        $this->assertStringContainsString('namespace app\models\flyo;', $code);
        $this->assertStringContainsString(TypeGenerator::MARKER, $code);
    }

    public function testTheNamespaceAndThePathCanBeGivenAsOption()
    {
        Module::setInstance(new Module('flyo', null, ['token' => 'foobar']));

        $this->assertSame(ExitCode::OK, $this->generate(['namespace' => 'app\\flyo', 'path' => $this->path]));
        $this->assertStringContainsString('namespace app\flyo;', (string) file_get_contents($this->path . '/BlockHero.php'));
    }

    public function testTheModuleIsFoundInTheApplicationConfiguration()
    {
        // the command is registered in the controllerMap of a console application, so it does not run inside
        // of the module and the module has not been bootstrapped either
        Yii::$app->setModule('flyo', [
            'class' => Module::class,
            'token' => 'foobar',
            'typesNamespace' => 'app\\models\\flyo',
            'typesPath' => $this->path,
        ]);

        $this->assertSame(ExitCode::OK, $this->generate());
        $this->assertFileExists($this->path . '/BlockHero.php');
    }

    public function testAMissingNamespaceIsAConfigurationError()
    {
        Module::setInstance(new Module('flyo', null, ['token' => 'foobar']));

        $this->assertSame(ExitCode::CONFIG, $this->generate());
        $this->assertDirectoryDoesNotExist($this->path);
    }

    public function testDryRunOnlyListsTheFiles()
    {
        $this->setModuleInstance();

        $controller = $this->controller();

        $this->assertSame(ExitCode::OK, $this->generate(['dryRun' => true], $controller));
        $this->assertDirectoryDoesNotExist($this->path);
        $this->assertStringContainsString('BlockHero.php', $controller->output);
        $this->assertStringContainsString('Nothing has been written', $controller->output);
    }

    public function testASecondRunDoesNotTouchUnchangedFiles()
    {
        $this->setModuleInstance();

        $controller = $this->controller();
        $this->generate([], $controller);

        $this->assertStringContainsString('13 created, 0 updated, 0 unchanged', $controller->output);

        $controller = $this->controller();
        $this->generate([], $controller);

        $this->assertStringContainsString('0 created, 0 updated, 13 unchanged', $controller->output);
    }

    public function testCleanRemovesGeneratedFilesOfAPreviousRunOnly()
    {
        $this->setModuleInstance();

        FileHelper::createDirectory($this->path);
        file_put_contents($this->path . '/BlockGone.php', "<?php\n\n/*\n * " . TypeGenerator::MARKER . "\n */\n");
        file_put_contents($this->path . '/MyOwnClass.php', "<?php\n");

        $controller = $this->controller();

        $this->assertSame(ExitCode::OK, $this->generate(['clean' => true], $controller));
        $this->assertStringContainsString('removed BlockGone.php', $controller->output);
        $this->assertFileDoesNotExist($this->path . '/BlockGone.php');
        $this->assertFileExists($this->path . '/MyOwnClass.php');
        $this->assertFileExists($this->path . '/BlockHero.php');
    }

    public function testAnInvalidSchemaFileIsReported()
    {
        $this->setModuleInstance();

        $controller = $this->controller();

        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $this->generate(['schemaFile' => __DIR__ . '/Data/nope.json'], $controller));
        $this->assertStringContainsString('does not exist or is not readable', $controller->output);
    }

    private function setModuleInstance(): void
    {
        Module::setInstance(new Module('flyo', null, [
            'token' => 'foobar',
            'typesNamespace' => 'app\\models\\flyo',
            'typesPath' => $this->path,
        ]));
    }

    private function controller(): SilentTypesController
    {
        return new SilentTypesController('flyo-types', Yii::$app);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function generate(array $params = [], ?SilentTypesController $controller = null): int
    {
        $controller = $controller ?: $this->controller();

        return (int) $controller->runAction('generate', array_merge(['schemaFile' => __DIR__ . '/Data/schemas.json'], $params));
    }
}
