<?php

namespace Flyo\Yii;

use Flyo\Api\ConfigApi;
use Flyo\Api\VersionApi;
use Flyo\Configuration;
use Flyo\Model\ConfigResponse;
use Flyo\Model\Page;
use Flyo\Model\VersionResponse;
use Flyo\ObjectSerializer;
use Flyo\Yii\Cache\VersionCacheDependency;
use Throwable;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\base\Module as BaseModule;
use yii\web\Application;
use yii\web\Response;
use yii\web\UrlRule;
use yii\web\View;

/**
 * @property ConfigResponse $config
 * @property Page $currentPage
 */
class Module extends BaseModule implements BootstrapInterface
{
    /**
     * @var string The [[Flyo\Yii\Events\PageResolveEvent]] that is triggered when a page is resolved. Only successfull resolutions trigger this event.
     */
    public const EVENT_PAGE_RESOLVE = 'pageResolve';

    /**
     * @var string The UMD build of the nitro js bridge which is registered when live edit is enabled, see [[$liveEditBridgeUrl]].
     */
    public const LIVE_EDIT_BRIDGE_URL = 'https://unpkg.com/@flyo/nitro-js-bridge@1/dist/nitro-js-bridge.umd.cjs';

    public $controllerNamespace = 'Flyo\Yii\Controllers';

    /**
     * @var string If defined, the configuration will use the given host instead of the default one. Ensure the host contains the version information like `localflyo.com/nitro/v1` without trailing slash.
     */
    public $host;

    /**
     * @var string The flyo api token from the flyo.cloud dashboard.
     */
    public $token;


    /**
     * @var boolean If enabled, and the application has configured a cache component, the page will be cached on the server side for [[$cacheDuration]] seconds.
     */
    public $serverPageCache = true;

    /**
     * @var int If enabled, and the application has configured a cache component, the page will be cached on the server side for this many seconds.
     */
    public $serverPageCacheDuration = 3600; // 1h

    /**
     * @var boolean Whether a CDN cache header should be sent for pages or not, if enabled in production the page will be cached for [[$cdnCacheDuration]] seconds in
     * the CDN edge cache. In order to disable CDN Caching for a specific action you can set `Module::getInstance()->cdnCache = false;` there.
     */
    public $cdnCache = true;

    /**
     * @var int Whether a CDN cache header should be sent for pages or not, if enabled in production the page will be cached for this many seconds in
     * the CDN edge cache. Current supported CDNs are Vercel and generic CDN-Cache-Control.
     */
    public $cdnCacheDuration = 1800; // 30min

    /**
     * @var boolean Whether a client cache header should be sent for pages or not, if enabled in production the page will be cached for 30mins in
     * the clients browser cache.
     */
    public $clientHttpCache = true;

    /**
     * @var int The duration in seconds for the client cache header, if enabled in production the page will be cached for this many seconds in
     * the clients browser cache.
     */
    public $clientHttpCacheDuration = 1800; // 30min

    /**
     * @var callable Additinal variation informations for the page, for example if you have a custom query param somewhere else:
     *
     * 'cacheVariation' => function() {
     *    return Yii::$app->request->getQueryParam('slug');
     * },
     *
     */
    public $cacheVariation;

    /**
     * @var array By default we only allow GET requests for all defined url rules, if you want to allow other request methods you can define them here.
     * Adding ['GET', 'POST'] can be useful for example if you want to use a form block inside a page.
     */
    public $urlRuleVerbs = ['GET'];

    /**
     * @var boolean|null Whether the live edit integration (nitro js bridge) should be registered in rendered pages or not.
     * By default live edit is enabled in every environment except production, set to `true` or `false` in order to force the behavior.
     */
    public $liveEdit;

    /**
     * @var string The url to the nitro js bridge which is registered when live edit is enabled, can be changed in order to self host the bridge.
     */
    public $liveEditBridgeUrl = self::LIVE_EDIT_BRIDGE_URL;

    /**
     * @var string|null Namespace of the models which are generated from the openapi schemas of your flyo
     * project, for example `App\Flyo\Model`. When defined, a block is hydrated into the model
     * `{namespace}\Block{Component}` (if that class exists) and the widgets pass that model to your view
     * instead of the generic `Flyo\Model\Block`, so a view reads typed values:
     * `$block->getContent()->getHeadline()`.
     *
     * This module does not generate the models, use the openapi generator of your choice on the schemas
     * endpoint of your project (`/openapi/schemas`) and autoload the result. Nothing is required from the
     * models except that they can be hydrated, blocks without a model keep the generic block model, see
     * [[$modelHydrator]] and `docs/typed-models.md`.
     */
    public $blockModelNamespace;

    /**
     * @var array<string, class-string> Explicit map of the block component name to its generated model, wins
     * over the convention of [[$blockModelNamespace]]:
     *
     * ```php
     * 'blockModels' => [
     *     'Hero' => \App\Flyo\Model\BlockHero::class,
     * ],
     * ```
     */
    public $blockModels = [];

    /**
     * @var string|null Namespace of the generated models for the detail data (`model`) of an entity, the
     * entity type `person` is hydrated into `{namespace}\EntityPerson` if that class exists, see
     * [[$entityModels]].
     */
    public $entityModelNamespace;

    /**
     * @var array<string, class-string> Explicit map of the entity type to the generated model of its detail
     * data, wins over the convention of [[$entityModelNamespace]]:
     *
     * ```php
     * 'entityModels' => [
     *     'person' => \App\Flyo\Model\EntityPerson::class,
     * ],
     * ```
     */
    public $entityModels = [];

    /**
     * @var callable|null Hydrates the json of the api response into one of the generated models above:
     *
     * ```php
     * 'modelHydrator' => fn (string $class, mixed $data): ?object => $mySerializer->denormalize($data, $class),
     * ```
     *
     * By default the [[ObjectSerializer]] of the flyo php sdk is used, which understands the models of the
     * openapi generator (the same generator the sdk itself is built with). Configure a hydrator when your
     * models come from another generator, then the module only checks that the class exists and leaves the
     * hydration to you. Returning null falls back to the untyped data.
     */
    public $modelHydrator;

    /**
     * Whether live edit should be registered in rendered pages or not, see [[$liveEdit]].
     */
    public function getIsLiveEditEnabled(): bool
    {
        return $this->liveEdit === null ? !YII_ENV_PROD : (bool) $this->liveEdit;
    }

    public function init()
    {
        parent::init();

        if (empty($this->token)) {
            throw new InvalidConfigException("The token param can not be empty for flyo nitro module.");
        }
    }

    private $_config;

    public function setConfig(ConfigResponse $config)
    {
        $this->_config = $config;
    }

    public function getConfig(): ConfigResponse
    {
        return $this->_config;
    }

    private $_currentPage;

    public function setCurrentPage(Page $page)
    {
        $this->_currentPage = $page;
    }

    public function getCurrentPage(): ?Page
    {
        return $this->_currentPage;
    }

    /**
     * @var array<string, string|false> Resolved model classes, false when there is no model for the key.
     */
    private array $_models = [];

    /**
     * Hydrates the given block into your own generated block model, see [[$blockModels]].
     *
     * Returns the given block unchanged when no model is configured for its component, when the configured
     * model does not exist or when the hydration fails outside of debug mode. Therefore adding your own
     * models never breaks the rendering of a block which has no model (yet).
     *
     * @param object $block Any block representation, usually a `Flyo\Model\Block`.
     */
    public function resolveBlockModel(object $block): object
    {
        $class = $this->resolveModelClass('block', Accessor::component($block), $this->blockModels, $this->blockModelNamespace, 'Block');

        if ($class === null) {
            return $block;
        }

        $model = $this->hydrate($class, ObjectSerializer::sanitizeForSerialization($block), $block);

        return is_object($model) ? $model : $block;
    }

    /**
     * Hydrates the detail data (`model`) of the given entity into your own generated entity model, see
     * [[$entityModels]].
     *
     * Returns the untyped detail data when no model is configured for the entity type, when the configured
     * model does not exist or when the hydration fails outside of debug mode.
     *
     * @param object $entity Usually a `Flyo\Model\Entity`.
     */
    public function resolveEntityModel(object $entity): mixed
    {
        $model = Accessor::model($entity);
        $interface = Accessor::read($entity, 'entity');
        $type = is_object($interface) ? (string) Accessor::read($interface, 'entity_type', '') : '';

        $class = $this->resolveModelClass('entity', $type, $this->entityModels, $this->entityModelNamespace, 'Entity');

        if ($class === null || !is_object($model)) {
            return $model;
        }

        return $this->hydrate($class, $model, $model);
    }

    /**
     * @param string $scope Either `block` or `entity`, only used to separate the cache and the log message.
     * @param string $key The block component or the entity type.
     * @param array<string, class-string> $map
     * @param string|null $namespace
     * @param string $prefix The class name prefix of the convention, `Block` or `Entity`.
     */
    private function resolveModelClass(string $scope, string $key, array $map, ?string $namespace, string $prefix): ?string
    {
        if ($key === '') {
            return null;
        }

        $cacheKey = $scope . '/' . $key;

        if (!array_key_exists($cacheKey, $this->_models)) {
            $class = $map[$key] ?? ($namespace === null ? null : rtrim($namespace, '\\') . '\\' . $prefix . ucfirst($key));

            $this->_models[$cacheKey] = $class !== null && $this->isSupportedModel($class) ? $class : false;

            if ($class !== null && $this->_models[$cacheKey] === false) {
                Yii::warning("The model {$class} of the {$scope} '{$key}' does not exist or can not be hydrated, the untyped data is used instead.", __METHOD__);
            }
        }

        $class = $this->_models[$cacheKey];

        return $class === false ? null : $class;
    }

    /**
     * @param class-string $class
     */
    private function hydrate(string $class, mixed $data, mixed $fallback): mixed
    {
        try {
            $model = $this->modelHydrator === null
                ? ObjectSerializer::deserialize($data, $class)
                : call_user_func($this->modelHydrator, $class, $data);

            return $model ?? $fallback;
        } catch (Throwable $e) {
            // models which have not been regenerated after a schema change must not break production
            if (YII_DEBUG) {
                throw $e;
            }

            Yii::warning("Unable to hydrate the model {$class}: {$e->getMessage()}", __METHOD__);

            return $fallback;
        }
    }

    /**
     * Whether the given model can be hydrated or not. With a [[$modelHydrator]] the class only has to exist,
     * the default hydrator additionally requires the static api of an openapi generator model, so a class
     * which can not be hydrated results in a warning instead of a fatal error.
     */
    private function isSupportedModel(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        if ($this->modelHydrator !== null) {
            return true;
        }

        return defined($class . '::DISCRIMINATOR')
            && is_callable([$class, 'openAPITypes'])
            && is_callable([$class, 'setters'])
            && is_callable([$class, 'attributeMap'])
            && is_callable([$class, 'isNullable']);
    }

    private function getNitroConfig(): ConfigResponse
    {
        Yii::beginProfile('flyo-config', __METHOD__);
        $config = (new ConfigApi(null, Configuration::getDefaultConfiguration()))->config();
        Yii::endProfile('flyo-config', __METHOD__);
        return $config;
    }

    private static $versionApi;

    /**
     * @return VersionResponse
     */
    public static function getVersionApi(): VersionResponse
    {
        if (self::$versionApi === null) {
            Yii::beginProfile('flyo-version', __METHOD__);
            $versionApi = (new VersionApi(null, Configuration::getDefaultConfiguration()))->version();
            Yii::endProfile('flyo-version', __METHOD__);

            Yii::debug([
                'version' => $versionApi->getVersion(),
                'last_updated_at' => date("d.m.Y H:i", $versionApi->getUpdatedAt()),
            ], __METHOD__);

            self::$versionApi = $versionApi;
        }

        return self::$versionApi;
    }

    public function bootstrap($app)
    {
        /** @var Application $app */
        $config = new Configuration();
        $config->setApiKey('token', $this->token);

        if ($this->host) {
            $config->setHost($this->host);
        }

        Configuration::setDefaultConfiguration($config);

        // Live edit is a pure frontend concern: whenever a page is rendered by a web application the nitro js bridge
        // and its boot script are registered. This is independent of whether a project uses the Editable widget or not.
        if ($app instanceof Application && $this->getIsLiveEditEnabled()) {
            Event::on(View::class, View::EVENT_BEGIN_PAGE, function (Event $event) {
                if ($event->sender instanceof View) {
                    LiveEdit::register($event->sender, $this->liveEditBridgeUrl);
                }
            });
        }

        $configApi = YII_ENV_PROD && $this->serverPageCache ? Yii::$app->cache->getOrSet(['flyo', 'config'], fn () => $this->getNitroConfig(), $this->serverPageCacheDuration, new VersionCacheDependency()) : $this->getNitroConfig();

        $this->setConfig($configApi);

        $rules = [];
        foreach ($this->config->getPages() as $page) {
            $rules[] = new UrlRule(['verb' => $this->urlRuleVerbs, 'pattern' => '<path:('.$page.')>', 'route' => "{$this->id}/nitro/index"]);
        }

        // To ensure proper prioritization, it is essential to prepend the rules. Otherwise, entity rules might take precedence over pages.
        $app->urlManager->addRules($rules, false);

        if (YII_ENV_PROD) {
            $app->response->on(Response::EVENT_BEFORE_SEND, function (Event $event) {
                // its possible that during the runtime the cdnCache is disabled for specific actions
                // therefore we need to check it again here
                if (Module::getInstance()->cdnCache) {
                    /** @var Response $sender */
                    $sender = $event->sender;
                    $sender->headers->set('Vercel-CDN-Cache-Control', "max-age={$this->cdnCacheDuration}");
                    $sender->headers->set('CDN-Cache-Control', "max-age={$this->cdnCacheDuration}");
                } else {
                    /** @var Response $sender */
                    $sender = $event->sender;
                    // explicitly disable cdn caching but client caching can still be active
                    $sender->headers->set('Vercel-CDN-Cache-Control', 'no-store');
                    $sender->headers->set('CDN-Cache-Control', 'no-store');
                }
            });
        }
    }
}
