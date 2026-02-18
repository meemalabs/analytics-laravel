<?php

namespace Meemalabs\Analytics\Tests;

use Meemalabs\Analytics\AnalyticsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AnalyticsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('analytics.token', 'ak_test_token');
        $app['config']->set('analytics.site_id', 'test-site');
        $app['config']->set('analytics.environment', 'testing');
        $app['config']->set('analytics.enabled', true);
    }
}
