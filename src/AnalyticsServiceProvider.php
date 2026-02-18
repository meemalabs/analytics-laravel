<?php

namespace Meemalabs\Analytics;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\NullLogger;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/analytics.php', 'analytics');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/analytics.php' => config_path('analytics.php'),
            ], 'analytics-config');
        }

        Log::extend('analytics', function (array $app, array $config) {
            $enabled = config('analytics.enabled', true);
            $token = config('analytics.token', '');

            if (! $enabled || empty($token)) {
                return new NullLogger;
            }

            $level = Level::fromName(
                $config['level'] ?? config('analytics.level', 'error')
            );

            $handler = new AnalyticsLogHandler(
                token: $token,
                endpoint: config('analytics.endpoint', ''),
                siteId: config('analytics.site_id', ''),
                environment: config('analytics.environment', 'production'),
                level: $level,
            );

            return new Logger('analytics', [$handler]);
        });
    }
}
