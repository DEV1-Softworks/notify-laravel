<?php

namespace Dev1\NotifyLaravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Dev1\NotifyLaravel\NotifyServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [NotifyServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('notify.default', 'fcm');
        $app['config']->set('notify.clients', []);
    }
}
