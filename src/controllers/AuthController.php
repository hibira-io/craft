<?php

namespace hibira\craft\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use hibira\craft\events\AuthorizeChannelEvent;
use hibira\craft\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Signs private and presence channel subscriptions for pusher-js
 * (actions/hibira/auth).
 */
class AuthController extends Controller
{
    public const EVENT_AUTHORIZE_CHANNEL = 'authorizeChannel';

    protected array|int|bool $allowAnonymous = ['index'];

    public $enableCsrfValidation = false;

    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $socketId = (string)$request->getBodyParam('socket_id', '');
        $channel = (string)$request->getBodyParam('channel_name', '');
        if (!preg_match('/^\d+\.\d+$/', $socketId) || !preg_match('/^(private|presence)-[A-Za-z0-9_\-=@,.;]+$/', $channel)) {
            throw new ForbiddenHttpException('Invalid channel or socket.');
        }
        $client = Plugin::getInstance()->client;
        $user = Craft::$app->getUser()->getIdentity();
        $decision = $this->decide($channel, $user);
        if (!$decision->allowed) {
            throw new ForbiddenHttpException('Not allowed to subscribe to this channel.');
        }
        if (str_starts_with($channel, 'presence-')) {
            if ($decision->userId === null || $decision->userId === '') {
                throw new ForbiddenHttpException('A presence channel needs a user.');
            }
            $auth = $client->authorizePresenceChannel($channel, $socketId, $decision->userId, $decision->userInfo);
        } else {
            $auth = $client->authorizeChannel($channel, $socketId);
        }
        if ($auth === null) {
            throw new ForbiddenHttpException('Hibira is not configured.');
        }
        $this->response->format = Response::FORMAT_RAW;
        $this->response->getHeaders()->set('Content-Type', 'application/json');
        $this->response->content = $auth;
        return $this->response;
    }

    private function decide(string $channel, ?\craft\elements\User $user): AuthorizeChannelEvent
    {
        $event = new AuthorizeChannelEvent(['channel' => $channel, 'user' => $user]);
        if (preg_match('/^presence-craft-entry-(\d+)$/', $channel, $m) && $user !== null) {
            $entry = Entry::find()->id((int)$m[1])->status(null)->site('*')->one();
            if ($entry !== null && Craft::$app->getElements()->canView($entry, $user)) {
                $event->allowed = true;
                $event->userId = (string)$user->id;
                $photo = $user->getPhoto()?->getUrl(['width' => 64, 'height' => 64]);
                $event->userInfo = array_filter(['name' => $user->getFriendlyName() ?? $user->username, 'photo' => $photo]);
            }
        } elseif (preg_match('/^private-craft-order-([a-f0-9]{32})$/', $channel, $m)) {
            $event->allowed = Plugin::getInstance()->orderExists($m[1]);
        } elseif ($channel === 'private-craft-cp-orders' && $user !== null) {
            $event->allowed = $user->can('commerce-manageOrders');
        }
        if ($this->hasEventHandlers(self::EVENT_AUTHORIZE_CHANNEL)) {
            $this->trigger(self::EVENT_AUTHORIZE_CHANNEL, $event);
        }
        return $event;
    }
}
