<?php

namespace hibira\craft\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

/**
 * Plugin settings. appId, key, secret and host accept environment variables
 * ($HIBIRA_APP_SECRET), so the secret can stay out of project config.
 */
class Settings extends Model
{
    public string $appId = '$HIBIRA_APP_ID';
    public string $key = '$HIBIRA_APP_KEY';
    public string $secret = '$HIBIRA_APP_SECRET';
    public string $host = 'ws.hibira.io';
    public string $cluster = 'hkg';
    public bool $cpPresence = true;
    public bool $liveEntries = true;
    public bool $commerceOrders = true;

    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['appId', 'key', 'secret', 'host'],
            ],
        ];
    }

    protected function defineRules(): array
    {
        return [
            [['host', 'cluster'], 'required'],
            [['appId', 'key', 'secret', 'host', 'cluster'], 'string', 'max' => 255],
            [['cpPresence', 'liveEntries', 'commerceOrders'], 'boolean'],
        ];
    }
}
