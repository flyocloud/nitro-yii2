<?php

namespace Flyo\Yii\Events;

use Flyo\Model\Page;
use yii\base\Event;

class PageResolveEvent extends Event
{
    public Page $page;
}
