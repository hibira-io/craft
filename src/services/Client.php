<?php

namespace hibira\craft\services;

use Craft;
use craft\helpers\App;
use hibira\craft\Plugin;
use Pusher\Pusher;
use yii\base\BaseObject;

/**
 * Publishes events to Hibira and signs channel subscriptions, with the
 * Pusher PHP library pointed at the Hibira host.
 */
class Client extends BaseObject
{
    private ?Pusher $pusher = null;
    private ?string $signature = null;

    /** Whether the app ID, key and secret are all set. */
    public function isConfigured(): bool
    {
        $c = $this->credentials();
        return $c['appId'] !== '' && $c['key'] !== '' && $c['secret'] !== '';
    }

    /**
     * @return array{appId: string, key: string, secret: string, host: string, cluster: string}
     */
    public function credentials(): array
    {
        $s = Plugin::getInstance()->getSettings();
        $host = (string)App::parseEnv($s->host);
        $host = preg_replace('#^(https?|wss?)://#', '', rtrim(trim($host), '/')) ?: 'ws.hibira.io';
        return [
            'appId' => trim((string)App::parseEnv($s->appId)),
            'key' => trim((string)App::parseEnv($s->key)),
            'secret' => trim((string)App::parseEnv($s->secret)),
            'host' => $host,
            'cluster' => $s->cluster ?: 'hkg',
        ];
    }

    /** The settings the browser needs (never the secret). */
    public function clientConfig(): array
    {
        $c = $this->credentials();
        [$host, $port, $tls] = $this->hostParts($c['host']);
        return [
            'key' => $c['key'],
            'wsHost' => $host,
            'wsPort' => $port,
            'wssPort' => $port,
            'forceTLS' => $tls,
            'cluster' => $c['cluster'],
            'enabledTransports' => ['ws', 'wss'],
            'authEndpoint' => \craft\helpers\UrlHelper::actionUrl('hibira/auth'),
        ];
    }

    /**
     * Publishes an event. Returns false (and logs) instead of throwing, so a
     * Hibira outage never breaks saving an entry.
     *
     * @param string|string[] $channels
     */
    public function trigger(string|array $channels, string $event, array|string $data = []): bool
    {
        $pusher = $this->pusher();
        if ($pusher === null) {
            return false;
        }
        try {
            $pusher->trigger((array)$channels, $event, $data);
            return true;
        } catch (\Throwable $e) {
            Craft::warning('Hibira trigger failed: ' . $e->getMessage(), 'hibira');
            return false;
        }
    }

    /** Signs a private channel subscription (JSON for pusher-js). */
    public function authorizeChannel(string $channel, string $socketId): ?string
    {
        return $this->pusher()?->authorizeChannel($channel, $socketId);
    }

    /** Signs a presence channel subscription (JSON for pusher-js). */
    public function authorizePresenceChannel(string $channel, string $socketId, string $userId, array $userInfo): ?string
    {
        return $this->pusher()?->authorizePresenceChannel($channel, $socketId, $userId, $userInfo);
    }

    private function pusher(): ?Pusher
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $c = $this->credentials();
        $sig = md5(serialize($c));
        if ($this->pusher === null || $this->signature !== $sig) {
            [$host, $port, $tls] = $this->hostParts($c['host']);
            $this->pusher = new Pusher($c['key'], $c['secret'], $c['appId'], [
                'host' => $host,
                'port' => $port,
                'scheme' => $tls ? 'https' : 'http',
                'useTLS' => $tls,
                'cluster' => $c['cluster'],
                'timeout' => 5,
            ]);
            $this->signature = $sig;
        }
        return $this->pusher;
    }

    /**
     * host, port and TLS from "ws.hibira.io" or "localhost:6001" (plain,
     * for local development).
     *
     * @return array{0: string, 1: int, 2: bool}
     */
    private function hostParts(string $host): array
    {
        if (preg_match('/^(.+):(\d+)$/', $host, $m)) {
            $port = (int)$m[2];
            return [$m[1], $port, $port === 443];
        }
        return [$host, 443, true];
    }
}
