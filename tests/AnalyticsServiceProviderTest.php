<?php

namespace Meemalabs\Analytics\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Psr\Log\NullLogger;

class AnalyticsServiceProviderTest extends TestCase
{
    public function test_config_is_merged(): void
    {
        $this->assertSame('ak_test_token', config('analytics.token'));
        $this->assertSame('https://analytics.test', config('analytics.endpoint'));
        $this->assertSame('test-site', config('analytics.site_id'));
        $this->assertSame('testing', config('analytics.environment'));
        $this->assertTrue(config('analytics.enabled'));
    }

    public function test_analytics_log_driver_is_registered(): void
    {
        $this->app['config']->set('logging.channels.analytics', [
            'driver' => 'analytics',
            'level' => 'error',
        ]);

        $logger = Log::channel('analytics');

        $this->assertNotNull($logger);
    }

    public function test_returns_null_logger_when_token_is_empty(): void
    {
        $this->app['config']->set('analytics.token', '');
        $this->app['config']->set('logging.channels.analytics', [
            'driver' => 'analytics',
            'level' => 'error',
        ]);

        $logger = Log::channel('analytics');
        $driver = $logger->getLogger();

        $this->assertInstanceOf(NullLogger::class, $driver);
    }

    public function test_returns_null_logger_when_disabled(): void
    {
        $this->app['config']->set('analytics.enabled', false);
        $this->app['config']->set('logging.channels.analytics', [
            'driver' => 'analytics',
            'level' => 'error',
        ]);

        $logger = Log::channel('analytics');
        $driver = $logger->getLogger();

        $this->assertInstanceOf(NullLogger::class, $driver);
    }

    public function test_returns_monolog_logger_when_properly_configured(): void
    {
        $this->app['config']->set('logging.channels.analytics', [
            'driver' => 'analytics',
            'level' => 'error',
        ]);

        $logger = Log::channel('analytics');
        $driver = $logger->getLogger();

        $this->assertInstanceOf(Logger::class, $driver);
    }

    public function test_channel_level_override_is_respected(): void
    {
        Http::fake(['https://analytics.test/errors/collect' => Http::response(null, 204)]);

        $this->app['config']->set('logging.channels.analytics', [
            'driver' => 'analytics',
            'level' => 'critical',
        ]);

        // Error is below critical, should not be sent
        Log::channel('analytics')->error('Not critical enough');

        Http::assertNothingSent();

        // Critical should be sent
        Log::channel('analytics')->critical('This is critical');

        Http::assertSent(function ($request) {
            return $request['message'] === 'This is critical';
        });
    }

    public function test_logging_an_exception_sends_report(): void
    {
        Http::fake(['https://analytics.test/errors/collect' => Http::response(null, 204)]);

        $this->app['config']->set('logging.channels.analytics', [
            'driver' => 'analytics',
            'level' => 'error',
        ]);

        $exception = new \RuntimeException('Test exception');
        Log::channel('analytics')->error('Test exception', [
            'exception' => $exception,
        ]);

        Http::assertSent(function ($request) {
            return $request['type'] === 'RuntimeException'
                && $request['message'] === 'Test exception'
                && ! empty($request['stack']);
        });
    }

    public function test_config_is_publishable(): void
    {
        $publishes = AnalyticsServiceProviderTest::getPublishableGroups();

        $this->assertArrayHasKey('analytics-config', $publishes);
    }

    private static function getPublishableGroups(): array
    {
        $groups = [];
        foreach (\Illuminate\Support\ServiceProvider::$publishGroups as $group => $paths) {
            $groups[$group] = $paths;
        }

        return $groups;
    }
}
