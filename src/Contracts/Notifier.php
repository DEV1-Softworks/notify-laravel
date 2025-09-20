<?php

namespace Dev1\NotifyLaravel\Contracts;

use Dev1\NotifyCore\DTO\PushResult;

interface Notifier
{
    public function send(array $target, array $payload, ?string $client = null): PushResult;
}
