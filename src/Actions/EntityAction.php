<?php

namespace Flyo\Yii\Actions;

use Flyo\Api\EntitiesApi;
use Flyo\ApiException;
use Flyo\Configuration;
use Flyo\Model\Entity;
use Flyo\Yii\Traits\MetaDataTrait;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;

class EntityAction extends Action
{
    use MetaDataTrait;
    /**
     * @var callable a PHP callable that will be called to return the entity data.
     * ```
     * return [
     *     'class' => EntityAction::class,
     *     'finder' => fn(EntitiesApi $api) => $api->entityBySlug(Yii::$app->request->get('slug')),
     * ]
     */
    public $finder;

    public function run()
    {
        try {
            $api = new EntitiesApi(null, Configuration::getDefaultConfiguration());

            /** @var Entity $entity */
            $entity = call_user_func($this->finder, $api);

            if (!$entity instanceof Entity) {
                throw new InvalidConfigException("The finder callable must return an instance of Entity.");
            }

            $this->registerEntity($entity);
            $this->registerMetricPixel($entity);

            return $this->controller->render($this->id, [
                'entity' => $entity,
            ]);

        } catch (ApiException $e) {
            throw new NotFoundHttpException('The requested entity detail does not exist.');
        }
    }
}
