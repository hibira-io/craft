<?php

namespace hibira\craft\web\assets\pusher;

use craft\web\AssetBundle;

/** pusher-js 8.6.0 (MIT), served from the plugin so no CDN is needed. */
class PusherAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->js = ['pusher.min.js'];
        parent::init();
    }
}
