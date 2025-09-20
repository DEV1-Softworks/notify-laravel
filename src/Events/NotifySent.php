<?php

namespace Dev1\NotifyLaravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotifySent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var PushResult */
    public $result;

    /** @var mixed */
    public $notifiable;

    /** @var \Illuminate\Notifications\Notification */
    public $notification;

    /** @var string|null */
    public $client;

    /** @var array */
    public $target;

    /** @var array */
    public $payload;

    public function __construct($result, $notifiable, $notification, $client, array $target, array $payload)
    {
        $this->result = $result;
        $this->notifiable = $notifiable;
        $this->notification = $notification;
        $this->client = $client;
        $this->target = $target;
        $this->payload = $payload;
    }
}
