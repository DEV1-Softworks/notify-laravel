<?php

namespace Dev1\NotifyLaravel\Channels;

use Dev1\NotifyLaravel\Contracts\Notifier;
use Dev1\NotifyLaravel\Events\NotifySent;
use Illuminate\Notifications\Notification;

class NotifyChannel
{
    public function __construct(private Notifier $notifier) {}

    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toDev1Notify')) {
            return;
        }

        $message = $notification->toDev1Notify($notifiable);

        $target  = isset($message['target'])  ? $message['target']  : null;
        $payload = isset($message['payload']) ? $message['payload'] : null;
        $client  = isset($message['client'])  ? $message['client']  : null;

        if (!$target) return;

        $result = $this->notifier->send($target, $payload, $client);
        event(new NotifySent($result, $notifiable, $notification, $client, $target, $payload));
    }
}
