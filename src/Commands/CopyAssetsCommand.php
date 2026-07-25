<?php

namespace Sandip\SecureStorage\Native\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

class CopyAssetsCommand extends NativePluginHookCommand
{
    protected $signature = 'nativephp:mobile-secure-storage:copy-assets';

    protected $description = 'Copy assets for the SecureStorage plugin';

    public function handle(): int
    {
        if ($this->isAndroid()) {
            $this->copyAndroidAssets();
        }

        if ($this->isIos()) {
            $this->copyIosAssets();
        }

        return self::SUCCESS;
    }

    protected function copyAndroidAssets(): void
    {
        $this->info('No Android assets to copy for SecureStorage');
    }

    protected function copyIosAssets(): void
    {
        $this->info('No iOS assets to copy for SecureStorage');
    }
}
