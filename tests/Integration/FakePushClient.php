<?php

namespace Dev1\NotifyLaravel\Tests\Integration;

use Dev1\NotifyCore\Contracts\PushClient;
use Dev1\NotifyCore\DTO\PushMessage;
use Dev1\NotifyCore\DTO\PushResult;
use Dev1\NotifyCore\DTO\PushTarget;

class FakePushClient implements PushClient
{
    /** @var \Dev1\NotifyCore\DTO\PushResult */
    private $result;

    /** @var array */
    public $last = ['message' => null, 'target' => null, 'times' => 0];

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function send(PushMessage $message, PushTarget $target): PushResult
    {
        $this->last['message'] = $message;
        $this->last['target']  = $target;
        $this->last['times']++;
        return $this->result;
    }
}
