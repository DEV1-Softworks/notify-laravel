<?php

namespace Dev1\NotifyLaravel\Tests;

use Dev1\NotifyCore\DTO\PushResult;
use Dev1\NotifyCore\Platform\AndroidOptions;
use Dev1\NotifyCore\Platform\ApnsOptions;
use Dev1\NotifyCore\Registry\ClientRegistry;
use Dev1\NotifyLaravel\Contracts\Notifier;
use Dev1\NotifyLaravel\Tests\Integration\FakePushClient;

class NotifierPayloadTest extends TestCase
{
    public function test_value_objects_are_converted_and_merged_with_platform_defaults()
    {
        $pushResult = $this->createMock(PushResult::class);

        $this->app['config']->set('notify.default', 'fake');
        $this->app['config']->set('notify.clients', [
            'fake' => [
                'platform_defaults' => [
                    'android' => ['priority' => 'HIGH'],
                    'apns'    => ['headers' => ['apns-priority' => '10']],
                ],
            ],
        ]);

        /** @var ClientRegistry $registry */
        $registry = $this->app->make(ClientRegistry::class);
        $fake = new FakePushClient($pushResult);
        $registry->register('fake', $fake);

        $android = AndroidOptions::make()->withTtl(900);
        $apns    = ApnsOptions::make()->withAps(['sound' => 'default']);

        $result = $this->app->make(Notifier::class)->send(
            ['token' => 'AAA', 'topic' => null, 'condition' => null],
            [
                'title'   => 'Hi',
                'body'    => 'There',
                'data'    => ['k' => 'v'],
                'android' => $android,
                'apns'    => $apns,
            ]
        );

        $this->assertSame($pushResult, $result);

        /** @var \Dev1\NotifyCore\DTO\PushMessage $message */
        $message = $fake->last['message'];
        $overrides = $message->platformOverrides;

        $this->assertSame('HIGH', $overrides['android']['priority']);
        $this->assertSame('900s', $overrides['android']['ttl']);
        $this->assertSame('10', $overrides['apns']['headers']['apns-priority']);
        $this->assertSame('default', $overrides['apns']['body']['aps']['sound']);

        /** @var \Dev1\NotifyCore\DTO\PushTarget $target */
        $target = $fake->last['target'];
        $this->assertSame('AAA', $target->token);
        $this->assertNull($target->topic);
        $this->assertNull($target->condition);
    }

    public function test_empty_string_target_fields_are_normalized_to_null()
    {
        $pushResult = $this->createMock(PushResult::class);

        $this->app['config']->set('notify.default', 'fake');
        $this->app['config']->set('notify.clients', ['fake' => []]);

        $registry = $this->app->make(ClientRegistry::class);
        $fake = new FakePushClient($pushResult);
        $registry->register('fake', $fake);

        $this->app->make(Notifier::class)->send(
            ['token' => '', 'topic' => 'news', 'condition' => ''],
            ['title' => 'x', 'body' => 'y']
        );

        /** @var \Dev1\NotifyCore\DTO\PushTarget $target */
        $target = $fake->last['target'];
        $this->assertNull($target->token);
        $this->assertSame('news', $target->topic);
        $this->assertNull($target->condition);
    }

    public function test_array_platform_overrides_are_accepted()
    {
        $pushResult = $this->createMock(PushResult::class);

        $this->app['config']->set('notify.default', 'fake');
        $this->app['config']->set('notify.clients', ['fake' => []]);

        $registry = $this->app->make(ClientRegistry::class);
        $fake = new FakePushClient($pushResult);
        $registry->register('fake', $fake);

        $this->app->make(Notifier::class)->send(
            ['token' => 'T'],
            [
                'android' => ['priority' => 'NORMAL'],
                'apns'    => ['headers' => ['apns-push-type' => 'alert']],
            ]
        );

        /** @var \Dev1\NotifyCore\DTO\PushMessage $message */
        $message = $fake->last['message'];
        $this->assertSame('NORMAL', $message->platformOverrides['android']['priority']);
        $this->assertSame('alert', $message->platformOverrides['apns']['headers']['apns-push-type']);
    }

    public function test_missing_payload_keys_do_not_warn()
    {
        $pushResult = $this->createMock(PushResult::class);

        $this->app['config']->set('notify.default', 'fake');
        $this->app['config']->set('notify.clients', ['fake' => []]);

        $registry = $this->app->make(ClientRegistry::class);
        $fake = new FakePushClient($pushResult);
        $registry->register('fake', $fake);

        // No title/body/data/android/apns keys at all.
        $this->app->make(Notifier::class)->send(['token' => 'T'], []);

        /** @var \Dev1\NotifyCore\DTO\PushMessage $message */
        $message = $fake->last['message'];
        $this->assertSame('', $message->title);
        $this->assertSame('', $message->body);
    }
}
