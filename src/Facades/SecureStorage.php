<?php

namespace Sandip\SecureStorage\Native\Facades;

use Illuminate\Support\Facades\Facade;

class SecureStorage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sandip\SecureStorage\Native\SecureStorage::class;
    }
}
