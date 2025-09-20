<?php

namespace Dev1\NotifyLaravel\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Notification;
use Dev1\NotifyLaravel\Contracts\Notifier;
use Dev1\NotifyLaravel\Events\NotifySent;

class NotifyChannelTest extends TestCase
{
    public function test_channel_dispatches_notify_sent_event_with_push_result()
    {
        Event::fake();

        $pushResult = $this->createMock(\Dev1\NotifyCore\DTO\PushResult::class);

        $this->app->bind(Notifier::class, function () use ($pushResult) {
            return new class($pushResult) implements Notifier {
                private $result;
                public function __construct($result)
                {
                    $this->result = $result;
                }
                public function send(array $target, array $payload, ?string $client = null): \Dev1\NotifyCore\DTO\PushResult
                {
                    \PHPUnit\Framework\Assert::assertArrayHasKey('title', $payload);
                    \PHPUnit\Framework\Assert::assertArrayHasKey('body', $payload);
                    return $this->result;
                }
            };
        });

        $notification = new class extends Notification {
            public function via($notifiable)
            {
                return ['dev1-notify'];
            }
            public function toDev1Notify($notifiable)
            {
                return [
                    'target' => [
                        'token' => $notifiable->fcm_token ?? 'AAA',
                        'topic' => null,
                        'condition' => null,
                    ],
                    'payload' => [
                        'title' => 'Hola',
                        'body'  => 'Ping',
                        'data'  => ['y' => 2],
                    ],
                    'client' => 'fcm',
                ];
            }
        };

        $notifiable = new class {
            public $fcm_token = 'AAA';
            use \Illuminate\Notifications\Notifiable;
            public function routeNotificationForMail()
            {
                return null;
            }
        };

        $notifiable->notify($notification);

        Event::assertDispatched(NotifySent::class, function ($e) use ($pushResult) {
            return $e->result === $pushResult
                && $e->client === 'fcm'
                && isset($e->target['token'])
                && isset($e->payload['title'])
                && $e->notification instanceof Notification;
        });
    }
}
