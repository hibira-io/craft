<?php

namespace hibira\craft;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use hibira\craft\models\Settings;
use hibira\craft\services\Client;
use hibira\craft\variables\HibiraVariable;
use hibira\craft\web\assets\cp\CpAsset;
use yii\base\Event;

/**
 * Hibira Realtime for Craft CMS.
 *
 * @property-read Client $client
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    /** Strings the control panel script translates with Craft.t(). */
    public const JS_STRINGS = [
        'Also editing:',
        '{name} saved this entry. Reload to see their changes.',
        '{name} saved this entry.',
        'Reload',
        'New order {reference}',
    ];

    public static function config(): array
    {
        return ['components' => ['client' => Client::class]];
    }

    public function init(): void
    {
        parent::init();
        Craft::$app->onInit(function () {
            $this->attachHandlers();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('hibira/_settings', [
            'settings' => $this->getSettings(),
            'configured' => $this->client->isConfigured(),
        ]);
    }

    /** Whether a Commerce order with this number exists. */
    public function orderExists(string $number): bool
    {
        $class = 'craft\\commerce\\elements\\Order';
        return class_exists($class) && $class::find()->number($number)->exists();
    }

    private function attachHandlers(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function (Event $e) {
            $e->sender->set('hibira', HibiraVariable::class);
        });

        Event::on(Entry::class, Element::EVENT_AFTER_SAVE, function (ModelEvent $e) {
            /** @var Entry $entry */
            $entry = $e->sender;
            $this->entrySaved($entry);
        });

        Event::on(Entry::class, Element::EVENT_DEFINE_SIDEBAR_HTML, function (DefineHtmlEvent $e) {
            /** @var Entry $entry */
            $entry = $e->sender;
            $e->html = $this->presenceHtml($entry) . $e->html;
        });

        $this->attachCommerceHandlers();
    }

    private function entrySaved(Entry $entry): void
    {
        if (!$this->client->isConfigured() || $entry->propagating || $entry->resaving || ElementHelper::isDraftOrRevision($entry)) {
            return;
        }
        $settings = $this->getSettings();
        $id = $entry->getCanonicalId();
        if ($settings->cpPresence && Craft::$app->getRequest()->getIsCpRequest()) {
            $user = Craft::$app->getUser()->getIdentity();
            $this->client->trigger('presence-craft-entry-' . $id, 'entry-saved', [
                'id' => $id,
                'userId' => $user ? (string)$user->id : null,
                'name' => $user ? ($user->getFriendlyName() ?? $user->username) : null,
            ]);
        }
        if ($settings->liveEntries && $entry->getStatus() === Entry::STATUS_LIVE) {
            $data = [
                'id' => $id,
                'title' => $entry->title,
                'url' => $entry->getUrl(),
                'section' => $entry->getSection()?->handle,
                'isNew' => $entry->firstSave,
                'dateUpdated' => $entry->dateUpdated?->format(DATE_ATOM),
            ];
            $this->client->trigger('craft-entry-' . $id, 'entry-updated', $data);
            if ($data['section']) {
                $this->client->trigger('craft-section-' . $data['section'], 'entry-saved', $data);
            }
        }
    }

    private function presenceHtml(Entry $entry): string
    {
        $settings = $this->getSettings();
        $user = Craft::$app->getUser()->getIdentity();
        if (!$settings->cpPresence || !$this->client->isConfigured() || $user === null || !$entry->id || $entry->getIsUnpublishedDraft()) {
            return '';
        }
        $view = Craft::$app->getView();
        $view->registerAssetBundle(CpAsset::class);
        $view->registerTranslations('hibira', self::JS_STRINGS);
        $config = $this->client->clientConfig() + [
            'channel' => 'presence-craft-entry-' . $entry->getCanonicalId(),
            'userId' => (string)$user->id,
        ];
        return Html::tag('div', '', [
            'id' => 'hibira-presence',
            'class' => 'hibira-presence',
            'data' => ['hibira' => Json::encode($config)],
            'aria-live' => 'polite',
        ]);
    }

    private function attachCommerceHandlers(): void
    {
        $histories = 'craft\\commerce\\services\\OrderHistories';
        $order = 'craft\\commerce\\elements\\Order';
        if (!class_exists($histories) || !class_exists($order)) {
            return;
        }
        Event::on($histories, $histories::EVENT_ORDER_STATUS_CHANGE, function ($e) {
            if (!$this->getSettings()->commerceOrders) {
                return;
            }
            $o = $e->order;
            $status = $o->getOrderStatus();
            $this->client->trigger('private-craft-order-' . $o->number, 'status', [
                'reference' => $o->reference,
                'status' => $status?->name,
                'handle' => $status?->handle,
                'color' => $status?->color,
            ]);
        });
        Event::on(View::class, View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE, function () {
            $this->registerCpOrderAlerts();
        });
        Event::on($order, $order::EVENT_AFTER_COMPLETE_ORDER, function (Event $e) {
            if (!$this->getSettings()->commerceOrders) {
                return;
            }
            $o = $e->sender;
            $this->client->trigger('private-craft-cp-orders', 'new-order', [
                'id' => $o->id,
                'reference' => $o->reference,
                'total' => $o->getTotalPriceAsCurrency(),
            ]);
        });
    }

    private function registerCpOrderAlerts(): void
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest() || !$request->getIsCpRequest() || !$this->getSettings()->commerceOrders || !$this->client->isConfigured()) {
            return;
        }
        $user = Craft::$app->getUser()->getIdentity();
        if ($user === null || !$user->can('commerce-manageOrders')) {
            return;
        }
        $view = Craft::$app->getView();
        $view->registerAssetBundle(CpAsset::class);
        $view->registerTranslations('hibira', self::JS_STRINGS);
        $view->registerJsVar('HibiraCraftOrders', $this->client->clientConfig() + ['channel' => 'private-craft-cp-orders']);
    }
}
