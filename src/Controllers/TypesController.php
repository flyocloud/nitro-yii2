<?php

namespace Flyo\Yii\Controllers;

use Flyo\Configuration;
use Flyo\Yii\Module;
use Flyo\Yii\Types\SchemaDocument;
use Flyo\Yii\Types\TypeGenerator;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;

/**
 * Generates the php type specs of your flyo blocks and entities.
 *
 * ```sh
 * ./yii flyo/types/generate
 * ```
 *
 * The command downloads the openapi schemas of your project and writes one class per schema into
 * [[Module::$typesPath]], see [[\Flyo\Yii\Types\TypeGenerator]]. The generated classes only describe the
 * json of the api response, they do not replace it, therefore they can be added to an existing project
 * without touching a single view, see [[\Flyo\Yii\Types\Shape]].
 *
 * Add it to your composer scripts to regenerate the type specs whenever your blocks change:
 *
 * ```json
 * "scripts": {
 *     "flyo:types": "./yii flyo/types/generate"
 * }
 * ```
 */
class TypesController extends Controller
{
    public $defaultAction = 'generate';

    /**
     * @var string|null The namespace of the generated classes, defaults to [[Module::$typesNamespace]].
     */
    public $namespace;

    /**
     * @var string|null The folder to write the classes to, defaults to [[Module::$typesPath]]. Aliases are
     * supported.
     */
    public $path;

    /**
     * @var string|null Reads the openapi document from a local json file instead of the api, useful in a ci
     * environment or to work offline.
     */
    public $schemaFile;

    /**
     * @var string|null The url of the openapi schemas endpoint, defaults to the host of the module.
     */
    public $url;

    /**
     * @var string The name of the generated class which maps a block component to its type spec, an empty
     * value disables it.
     */
    public $mapClass = 'Blocks';

    /**
     * @var bool Whether generated files of a previous run which are not generated anymore should be deleted
     * or not. Only files which contain the generated marker are removed.
     */
    public $clean = false;

    /**
     * @var bool Whether the files should be written or only listed.
     */
    public $dryRun = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['namespace', 'path', 'schemaFile', 'url', 'mapClass', 'clean', 'dryRun']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'n' => 'namespace',
            'p' => 'path',
            'f' => 'schemaFile',
            'u' => 'url',
        ]);
    }

    /**
     * Generates the type specs of all blocks and entities of your flyo project.
     */
    public function actionGenerate(): int
    {
        $module = $this->getFlyoModule();
        $namespace = trim((string) ($this->namespace ?? $module?->typesNamespace), '\\');

        if ($namespace === '') {
            $this->stderr("The namespace of the type specs is not configured, set the `typesNamespace` property of the flyo module or pass `--namespace`.\n", Console::FG_RED);

            return ExitCode::CONFIG;
        }

        $path = (string) Yii::getAlias((string) ($this->path ?? $module?->typesPath ?? '@app/models/flyo'));

        try {
            $document = $this->getDocument();
            $generator = new TypeGenerator($document, [
                'namespace' => $namespace,
                'mapClass' => (string) $this->mapClass,
            ]);
            $files = $generator->generate();
        } catch (Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Generating " . count($files) . " type specs of " . count($document->getSchemas()) . " schemas into {$path}\n\n");

        if ($this->dryRun) {
            foreach (array_keys($files) as $name) {
                $this->stdout("  {$name}\n");
            }

            $this->stdout("\nNothing has been written, remove `--dryRun` to generate the files.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        FileHelper::createDirectory($path);

        $created = 0;
        $updated = 0;

        foreach ($files as $name => $code) {
            $file = $path . DIRECTORY_SEPARATOR . $name;
            $exists = is_file($file);

            if ($exists && file_get_contents($file) === $code) {
                continue;
            }

            if (file_put_contents($file, $code) === false) {
                $this->stderr("Unable to write {$file}\n", Console::FG_RED);

                return ExitCode::IOERR;
            }

            $exists ? $updated++ : $created++;
        }

        $this->stdout("  {$created} created, {$updated} updated, " . (count($files) - $created - $updated) . " unchanged\n", Console::FG_GREEN);

        $removed = $this->clean ? $this->removeObsoleteFiles($path, array_keys($files)) : [];

        foreach ($removed as $name) {
            $this->stdout("  removed {$name}\n", Console::FG_YELLOW);
        }

        $components = $generator->getComponents();

        if ($components === []) {
            $this->stdout("\nNo block component has been detected in the openapi document, only the shapes have been generated. Apply them in a view with `MyShape::ofContent(\$block)`.\n", Console::FG_YELLOW);
        } else {
            $this->stdout("\nType specs for the components: " . implode(', ', array_keys($components)) . "\n");
        }

        $this->stdout("Ensure your composer autoload maps `{$namespace}` to `{$path}`.\n");

        return ExitCode::OK;
    }

    /**
     * The openapi document with the schemas of your project.
     */
    protected function getDocument(): SchemaDocument
    {
        if ($this->schemaFile !== null) {
            return SchemaDocument::fromFile((string) Yii::getAlias($this->schemaFile));
        }

        $configuration = $this->getConfiguration();

        if ($this->url !== null) {
            return SchemaDocument::fromUrl($this->url, $configuration->getApiKey('token'));
        }

        return SchemaDocument::fromConfiguration($configuration);
    }

    /**
     * The sdk configuration with the host and the token of the flyo module. Usually this is the default
     * configuration of [[Module::bootstrap()]], a console application which does not bootstrap the module
     * falls back to the properties of the module itself.
     */
    protected function getConfiguration(): Configuration
    {
        $configuration = Configuration::getDefaultConfiguration();
        $module = $this->getFlyoModule();

        if ($module === null || $configuration->getApiKey('token') !== null) {
            return $configuration;
        }

        $configuration = new Configuration();
        $configuration->setApiKey('token', (string) $module->token);

        if (!empty($module->host)) {
            $configuration->setHost($module->host);
        }

        return $configuration;
    }

    /**
     * Removes the files of a previous run which are not generated anymore. Only files which contain the
     * marker of the generator are touched, so handwritten classes in the same folder are kept.
     *
     * @param string[] $files The file names of the current run.
     * @return string[] The names of the removed files.
     */
    protected function removeObsoleteFiles(string $path, array $files): array
    {
        $removed = [];

        foreach (FileHelper::findFiles($path, ['only' => ['*.php'], 'recursive' => false]) as $file) {
            $name = basename($file);

            if (in_array($name, $files, true)) {
                continue;
            }

            $content = (string) file_get_contents($file);

            if (!str_contains($content, TypeGenerator::MARKER)) {
                continue;
            }

            if (unlink($file)) {
                $removed[] = $name;
            }
        }

        return $removed;
    }

    /**
     * The flyo module: either the module this controller runs in, the bootstrapped instance or, when the
     * command is registered in the `controllerMap` of a console application, the module of the application
     * configuration.
     */
    protected function getFlyoModule(): ?Module
    {
        if ($this->module instanceof Module) {
            return $this->module;
        }

        $instance = Module::getInstance();

        if ($instance !== null) {
            return $instance;
        }

        foreach (Yii::$app->getModules(false) as $id => $config) {
            $class = is_array($config) ? ($config['class'] ?? null) : (is_object($config) ? $config::class : $config);

            // only instantiate the flyo module, loading every module of an application could have side effects
            if (is_string($class) && is_a($class, Module::class, true) && Yii::$app->getModule($id) instanceof Module) {
                return Module::getInstance();
            }
        }

        return null;
    }
}
