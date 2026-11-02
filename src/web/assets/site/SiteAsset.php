<?php

namespace hibira\craft\web\assets\site;

use craft\web\AssetBundle;
use hibira\craft\web\assets\pusher\PusherAsset;

/** window.HibiraCraft for front-end templates. */
class SiteAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [PusherAsset::class];
        $this->js = ['site.js'];
        parent::init();
    }
}
