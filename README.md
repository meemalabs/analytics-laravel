# Analytics Laravel

A Laravel log driver that sends errors and log entries to your [ts-analytics](https://github.com/stacksjs/analytics) server. Works like Flare or Bugsnag — configure it once and Laravel's exception handler automatically reports errors through the logging pipeline.

## Installation

```bash
composer require meemalabs/analytics-laravel
```

The service provider is auto-discovered by Laravel.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=analytics-config
```

Add these to your `.env`:

```dotenv
ANALYTICS_TOKEN=ak_your_api_key
ANALYTICS_ENDPOINT=https://analytics.example.com
ANALYTICS_SITE_ID=my-site
```

Add the `analytics` channel to your `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'analytics'],
    ],

    'analytics' => [
        'driver' => 'analytics',
        'level' => 'error',
    ],
],
```

That's it. Exceptions and error-level log entries are now sent to your analytics server automatically.

## Config Reference

All options in `config/analytics.php`:

| Key | Env Var | Default | Description |
|-----|---------|---------|-------------|
| `token` | `ANALYTICS_TOKEN` | `''` | API key (must start with `ak_`) |
| `endpoint` | `ANALYTICS_ENDPOINT` | `''` | Base URL of your analytics server |
| `site_id` | `ANALYTICS_SITE_ID` | `''` | Site identifier |
| `environment` | `ANALYTICS_ENVIRONMENT` | `APP_ENV` | Environment name in reports |
| `enabled` | `ANALYTICS_ENABLED` | `true` | Kill switch to disable reporting |
| `level` | `ANALYTICS_LOG_LEVEL` | `error` | Minimum log level to report |

## How It Works

1. The package registers an `analytics` Monolog log driver via `Log::extend()`
2. When Laravel logs an error (or an exception is thrown), the log record reaches the `AnalyticsLogHandler`
3. If the log context contains a `Throwable`, a full error report is built with class name, message, stack trace, file, and line
4. Otherwise, a message-level report is built from the log message
5. The report is sent via HTTP POST to `{endpoint}/errors/collect` with the `X-Analytics-Token` header
6. The HTTP call uses a 5-second timeout and silently catches failures — logging never crashes the app

## Requirements

- PHP 8.2+
- Laravel 11+

## License

MIT
