<?php

namespace Flyo\Yii\Events;

use Flyo\Model\Page;
use yii\base\Event;

class OnPageResolveEvent extends Event
{
    public Page $page;
}
