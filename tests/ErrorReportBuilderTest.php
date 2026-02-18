<?php

namespace Meemalabs\Analytics\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use Meemalabs\Analytics\ErrorReportBuilder;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;

class ErrorReportBuilderTest extends TestCase
{
    private ErrorReportBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ErrorReportBuilder('test-site', 'testing');
    }

    public function test_from_exception_builds_complete_payload(): void
    {
        $exception = new RuntimeException('Something went wrong');
        $record = $this->makeLogRecord(Level::Error, 'RuntimeException', [
            'exception' => $exception,
        ]);

        $payload = $this->builder->fromException($exception, $record);

        $this->assertSame('Something went wrong', $payload['message']);
        $this->assertSame('RuntimeException', $payload['type']);
        $this->assertNotEmpty($payload['stack']);
        $this->assertSame($exception->getFile(), $payload['source']);
        $this->assertSame($exception->getLine(), $payload['line']);
        $this->assertSame(0, $payload['col']);
        $this->assertSame('server', $payload['browser']);
        $this->assertSame('', $payload['browserVersion']);
        $this->assertSame(PHP_OS_FAMILY, $payload['os']);
        $this->assertSame(0, $payload['screenWidth']);
        $this->assertSame(0, $payload['screenHeight']);
        $this->assertSame('laravel', $payload['framework']);
        $this->assertSame('0.1.0', $payload['sdkVersion']);
        $this->assertSame('testing', $payload['environment']);
        $this->assertSame('server', $payload['deviceType']);
        $this->assertIsArray($payload['tags']);
        $this->assertIsArray($payload['breadcrumbs']);
        $this->assertEmpty($payload['breadcrumbs']);
    }

    public function test_from_exception_uses_short_class_name_as_type(): void
    {
        $exception = new InvalidArgumentException('Bad input');
        $record = $this->makeLogRecord(Level::Error, 'Bad input', [
            'exception' => $exception,
        ]);

        $payload = $this->builder->fromException($exception, $record);

        $this->assertSame('InvalidArgumentException', $payload['type']);
    }

    public function test_from_exception_generates_deterministic_fingerprint(): void
    {
        $exception = new RuntimeException('Test error');
        $record = $this->makeLogRecord(Level::Error, 'Test error', [
            'exception' => $exception,
        ]);

        $first = $this->builder->fromException($exception, $record);
        $second = $this->builder->fromException($exception, $record);

        $this->assertSame($first['fingerprint'], $second['fingerprint']);
        $this->assertSame(32, strlen($first['fingerprint'])); // md5 hex length
    }

    public function test_from_exception_different_exceptions_produce_different_fingerprints(): void
    {
        $record = $this->makeLogRecord(Level::Error, 'error');

        $e1 = new RuntimeException('Error A');
        $e2 = new RuntimeException('Error B');

        $fp1 = $this->builder->fromException($e1, $record)['fingerprint'];
        $fp2 = $this->builder->fromException($e2, $record)['fingerprint'];

        $this->assertNotSame($fp1, $fp2);
    }

    public function test_from_message_builds_complete_payload(): void
    {
        $record = $this->makeLogRecord(Level::Error, 'Database connection failed');

        $payload = $this->builder->fromMessage($record);

        $this->assertSame('Database connection failed', $payload['message']);
        $this->assertSame('Error', $payload['type']);
        $this->assertSame('', $payload['stack']);
        $this->assertSame('', $payload['source']);
        $this->assertSame(0, $payload['line']);
        $this->assertSame(0, $payload['col']);
        $this->assertSame('server', $payload['browser']);
        $this->assertSame('laravel', $payload['framework']);
        $this->assertSame('testing', $payload['environment']);
        $this->assertSame('server', $payload['deviceType']);
    }

    public function test_from_message_uses_log_level_as_type(): void
    {
        $critical = $this->makeLogRecord(Level::Critical, 'Critical failure');
        $emergency = $this->makeLogRecord(Level::Emergency, 'System down');

        $this->assertSame('Critical', $this->builder->fromMessage($critical)['type']);
        $this->assertSame('Emergency', $this->builder->fromMessage($emergency)['type']);
    }

    public function test_from_message_generates_deterministic_fingerprint(): void
    {
        $record = $this->makeLogRecord(Level::Error, 'Same message');

        $first = $this->builder->fromMessage($record);
        $second = $this->builder->fromMessage($record);

        $this->assertSame($first['fingerprint'], $second['fingerprint']);
    }

    public function test_tags_include_site_id_and_server_context(): void
    {
        $record = $this->makeLogRecord(Level::Error, 'test');

        $payload = $this->builder->fromMessage($record);

        $this->assertSame('test-site', $payload['tags']['site_id']);
        $this->assertSame(PHP_VERSION, $payload['tags']['php_version']);
        $this->assertArrayHasKey('hostname', $payload['tags']);
        $this->assertArrayHasKey('log_channel', $payload['tags']);
    }

    public function test_custom_tags_from_context_are_merged(): void
    {
        $record = $this->makeLogRecord(Level::Error, 'test', [
            'tags' => ['user_id' => '42', 'request_id' => 'abc-123'],
        ]);

        $payload = $this->builder->fromMessage($record);

        $this->assertSame('42', $payload['tags']['user_id']);
        $this->assertSame('abc-123', $payload['tags']['request_id']);
        $this->assertSame('test-site', $payload['tags']['site_id']); // base tags still present
    }

    public function test_timestamp_format_is_iso8601_with_milliseconds(): void
    {
        $record = $this->makeLogRecord(Level::Error, 'test');

        $payload = $this->builder->fromMessage($record);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/',
            $payload['timestamp']
        );
    }

    public function test_payload_contains_all_required_error_report_fields(): void
    {
        $record = $this->makeLogRecord(Level::Error, 'test');
        $payload = $this->builder->fromMessage($record);

        $requiredFields = [
            'message', 'type', 'stack', 'source', 'line', 'col',
            'fingerprint', 'url', 'userAgent', 'browser', 'browserVersion',
            'os', 'osVersion', 'screenWidth', 'screenHeight', 'framework',
            'sdkVersion', 'environment', 'tags', 'breadcrumbs', 'timestamp',
            'deviceType',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $payload, "Missing field: {$field}");
        }
    }

    public function test_url_contains_artisan_prefix_in_console(): void
    {
        $record = $this->makeLogRecord(Level::Error, 'test');

        $payload = $this->builder->fromMessage($record);

        $this->assertStringStartsWith('artisan://', $payload['url']);
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
