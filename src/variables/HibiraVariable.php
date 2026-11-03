<?php

namespace hibira\craft\variables;

use Craft;
use hibira\craft\Plugin;
use hibira\craft\web\assets\site\SiteAsset;

/**
 * craft.hibira in Twig.
 *
 *   {% do craft.hibira.registerScript() %}
 *   <article data-hibira-entry="{{ entry.id }}">…</article>
 *   {% do craft.hibira.trigger('scores', 'goal', { team: 'home' }) %}
 */
class HibiraVariable
{
    /** Publishes an event (server side, from a template). */
    public function trigger(string|array $channels, string $event, array|string $data = []): bool
    {
        return Plugin::getInstance()->client->trigger($channels, $event, $data);
    }

    /** The pusher-js options for this site (never the secret). */
    public function config(): array
    {
        return Plugin::getInstance()->client->clientConfig();
    }

    /**
     * Adds pusher-js and window.HibiraCraft to the page. Elements with
     * data-hibira-entry="{id}" get a reload notice when that entry is saved.
     */
    public function registerScript(): void
    {
        $client = Plugin::getInstance()->client;
        if (!$client->isConfigured()) {
            return;
        }
        $view = Craft::$app->getView();
        $view->registerJsVar('HibiraCraftConfig', $client->clientConfig() + [
            'strings' => [
                'updated' => Craft::t('hibira', 'This page was updated.'),
                'reload' => Craft::t('hibira', 'Reload'),
            ],
        ]);
        $view->registerAssetBundle(SiteAsset::class);
    }
}
