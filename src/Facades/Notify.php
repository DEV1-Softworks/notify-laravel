<?php

namespace Dev1\NotifyLaravel\Facades;

use Dev1\NotifyLaravel\Contracts\Notifier;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PushResult send(string $channel, array $payload, ?string $client = null)
 */
class Notify extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Notifier::class;
    }
}
