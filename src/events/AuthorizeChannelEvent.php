<?php

namespace hibira\craft\events;

use craft\elements\User;
use yii\base\Event;

/**
 * Lets a site decide its own private and presence channels: set $allowed to
 * true, and for a presence channel also $userId and $userInfo.
 */
class AuthorizeChannelEvent extends Event
{
    public string $channel = '';
    public ?User $user = null;
    public bool $allowed = false;
    public ?string $userId = null;
    public array $userInfo = [];
}
