<?php

namespace Meemalabs\Analytics\Tests;

use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Meemalabs\Analytics\AnalyticsLogHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;

class AnalyticsLogHandlerTest extends TestCase
{
    public function test_sends_post_request_to_correct_endpoint(): void
    {
        Http::fake(['https://analytics.test/errors/collect' => Http::response(null, 204)]);

        $handler = new AnalyticsLogHandler(
            token: 'ak_test',
            endpoint: 'https://analytics.test',
            siteId: 'test-site',
            environment: 'testing',
        );

        $handler->handle($this->makeLogRecord(Level::Error, 'Test error'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://analytics.test/errors/collect'
                && $request->header('X-Analytics-Token')[0] === 'ak_test'
                && $request['message'] === 'Test error';
        });
    }

    public function test_sends_exception_payload_when_context_has_throwable(): void
    {
        Http::fake(['https://analytics.test/errors/collect' => Http::response(null, 204)]);

        $handler = new AnalyticsLogHandler(
            token: 'ak_test',
            endpoint: 'https://analytics.test',
            siteId: 'test-site',
            environment: 'testing',
        );

        $exception = new RuntimeException('DB connection lost');
        $record = $this->makeLogRecord(Level::Error, 'DB connection lost', [
            'exception' => $exception,
        ]);

        $handler->handle($record);

        Http::assertSent(function ($request) {
            return $request['type'] === 'RuntimeException'
                && $request['message'] === 'DB connection lost'
                && ! empty($request['stack'])
                && ! empty($request['source']);
        });
    }

    public function test_sends_message_payload_when_no_exception_in_context(): void
    {
        Http::fake(['https://analytics.test/errors/collect' => Http::response(null, 204)]);

        $handler = new AnalyticsLogHandler(
            token: 'ak_test',
            endpoint: 'https://analytics.test',
            siteId: 'test-site',
            environment: 'testing',
        );

        $handler->handle($this->makeLogRecord(Level::Error, 'Something failed'));

        Http::assertSent(function ($request) {
            return $request['type'] === 'Error'
                && $request['message'] === 'Something failed'
                && $request['stack'] === '';
        });
    }

    public function test_does_not_throw_on_http_failure(): void
    {
        Http::fake(['https://analytics.test/errors/collect' => Http::response(null, 500)]);

        $handler = new AnalyticsLogHandler(
            token: 'ak_test',
            endpoint: 'https://analytics.test',
            siteId: 'test-site',
            environment: 'testing',
        );

        // Should not throw
        $handler->handle($this->makeLogRecord(Level::Error, 'Test'));

        $this->assertTrue(true);
    }

    public function test_does_not_throw_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $handler = new AnalyticsLogHandler(
            token: 'ak_test',
            endpoint: 'https://analytics.test',
            siteId: 'test-site',
            environment: 'testing',
        );

        // Should not throw
        $handler->handle($this->makeLogRecord(Level::Error, 'Test'));

        $this->assertTrue(true);
    }

    public function test_respects_minimum_log_level(): void
    {
        Http::fake();

        $handler = new AnalyticsLogHandler(
            token: 'ak_test',
            endpoint: 'https://analytics.test',
            siteId: 'test-site',
            environment: 'testing',
            level: Level::Error,
        );

        // Warning is below Error, should not be sent
        $handler->handle($this->makeLogRecord(Level::Warning, 'Just a warning'));

        Http::assertNothingSent();
    }

    public function test_endpoint_trailing_slash_is_normalized(): void
    {
        Http::fake(['https://analytics.test/errors/collect' => Http::response(null, 204)]);

        $handler = new AnalyticsLogHandler(
            token: 'ak_test',
            endpoint: 'https://analytics.test/',
            siteId: 'test-site',
            environment: 'testing',
        );

        $handler->handle($this->makeLogRecord(Level::Error, 'Test'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://analytics.test/errors/collect';
        });
    }

    public function test_payload_includes_framework_and_sdk_version(): void
    {
        Http::fake(['https://analytics.test/errors/collect' => Http::response(null, 204)]);

        $handler = new AnalyticsLogHandler(
            token: 'ak_test',
            endpoint: 'https://analytics.test',
            siteId: 'test-site',
            environment: 'testing',
        );

        $handler->handle($this->makeLogRecord(Level::Error, 'Test'));

        Http::assertSent(function ($request) {
            return $request['framework'] === 'laravel'
                && $request['sdkVersion'] === '0.1.0'
                && $request['environment'] === 'testing'
                && $request['deviceType'] === 'server';
        });
    }

    private function makeLogRecord(Level $level, string $message, array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'analytics',
            level: $level,
            message: $message,
            context: $context,
        );
    }
}
