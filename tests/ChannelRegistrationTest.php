<?php

namespace Dev1\NotifyLaravel\Tests;

use Illuminate\Notifications\ChannelManager;
use Dev1\NotifyLaravel\Channels\NotifyChannel;

class ChannelRegistrationTest extends TestCase
{
    public function test_dev1_notify_channel_is_registered_and_resolvable()
    {
        /** @var ChannelManager $manager */
        $manager = $this->app->make(ChannelManager::class);

        $channel = $manager->driver('dev1-notify');

        $this->assertInstanceOf(
            NotifyChannel::class,
            $channel,
            'Channel dev1-notify is not registered correctly at ChannelManager.'
        );
    }
}
