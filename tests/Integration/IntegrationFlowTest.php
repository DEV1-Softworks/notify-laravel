<?php

namespace Dev1\NotifyLaravel\Tests\Integration;

use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;
use Dev1\NotifyLaravel\Tests\TestCase;
use Dev1\NotifyLaravel\Facades\Notify;
use Dev1\NotifyCore\Registry\ClientRegistry;
use Dev1\NotifyLaravel\Events\NotifySent;
use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Notification;

class IntegrationFlowTest extends TestCase
{
    public function test_facade_and_channel_flow_with_fake_client_default()
    {
        $pushResult = $this->createMock(\Dev1\NotifyCore\DTO\PushResult::class);

        /** @var ClientRegistry $registry */
        $registry = $this->app->make(ClientRegistry::class);

        $fake = new FakePushClient($pushResult);
        $registry->register('fake', $fake);

        $this->app['config']->set('notify.default', 'fake');

        $target  = ['token' => 'AAA', 'topic' => null, 'condition' => null];

        $payload = ['title' => 'Hola', 'body' => 'Ping', 'data' => ['k' => 'v'], 'android' => [], 'apns' => []];

        $result = Notify::send($target, $payload, null);

        $this->assertSame($pushResult, $result);
        $this->assertSame(1, $fake->last['times']);
        $this->assertNotNull($fake->last['message']);
        $this->assertNotNull($fake->last['target']);

        Event::fake();

        $notification = new class($target, $payload) extends Notification {
            private $t;
            private $p;
            public function __construct($t, $p)
            {
                $this->t = $t;
                $this->p = $p;
            }
            public function via($notifiable)
            {
                return ['dev1-notify'];
            }
            public function toDev1Notify($notifiable)
            {
                return ['target' => $this->t, 'payload' => $this->p, 'client' => null];
            }
        };

        $notifiable = new class {
            use \Illuminate\Notifications\Notifiable;
            public $fcm_token = 'AAA';
        };

        $notifiable->notify($notification);

        Event::assertDispatched(NotifySent::class, function ($e) use ($pushResult) {
            return $e->result === $pushResult
                && $e->client === null
                && isset($e->target['token'])
                && isset($e->payload['title']);
        });

        $this->assertSame(2, $fake->last['times']);
    }
}
