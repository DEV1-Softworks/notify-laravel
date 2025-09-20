<?php

namespace Dev1\NotifyCore\Auth;

use Dev1\NotifyLaravel\Contracts\Notifier;
use Dev1\NotifyLaravel\Facades\Notify;
use Dev1\NotifyLaravel\Tests\TestCase;

class NotifierFacadeTest extends TestCase
{
    public function test_facade_send_returns_push_result_and_passes_arguments()
    {
        $pushResult = $this->createMock(\Dev1\NotifyCore\DTO\PushResult::class);

        $this->app->bind(Notifier::class, function () use ($pushResult, &$captured) {
            return new class($pushResult, $captured) implements Notifier {
                private $result;
                private $captured;
                public function __construct($result, &$captured)
                {
                    $this->result = $result;
                    $this->captured = &$captured;
                }
                public function send(array $target, array $payload, ?string $client = null): \Dev1\NotifyCore\DTO\PushResult
                {
                    $this->captured['target'] = $target;
                    $this->captured['payload'] = $payload;
                    $this->captured['client'] = $client;
                    return $this->result;
                }
            };
        });

        $target = ['token' => 'AAA', 'topic' => null, 'condition' => null];
        $payload = ['title' => 'Hola', 'body' => 'Ping', 'data' => ['x' => 1]];
        $client = 'fcm';

        // Act
        $result = Notify::send($target, $payload, $client);

        // Assert
        $this->assertSame($pushResult, $result);
        $this->assertSame($target, $captured['target']);
        $this->assertSame($payload, $captured['payload']);
        $this->assertSame($client, $captured['client']);
    }
}
