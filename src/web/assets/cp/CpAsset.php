<?php

namespace hibira\craft\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;
use hibira\craft\web\assets\pusher\PusherAsset;

/** Editing presence, saved warnings and new order alerts in the control panel. */
class CpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [CraftCpAsset::class, PusherAsset::class];
        $this->js = ['cp.js'];
        $this->css = ['cp.css'];
        parent::init();
    }
}
