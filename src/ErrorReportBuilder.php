<?php

namespace Meemalabs\Analytics;

use Monolog\LogRecord;
use Throwable;

class ErrorReportBuilder
{
    private const SDK_VERSION = '0.1.0';

    public function __construct(
        private string $siteId,
        private string $environment,
    ) {}

    public function fromException(Throwable $e, LogRecord $record): array
    {
        $firstFrame = $this->getFirstFrame($e);

        return [
            'message' => $e->getMessage(),
            'type' => (new \ReflectionClass($e))->getShortName(),
            'stack' => $e->getTraceAsString(),
            'source' => $e->getFile(),
            'line' => $e->getLine(),
            'col' => 0,
            'fingerprint' => $this->fingerprint(get_class($e), $e->getMessage(), $firstFrame),
            'url' => $this->getUrl(),
            'userAgent' => 'Laravel/'.app()->version(),
            'browser' => 'server',
            'browserVersion' => '',
            'os' => PHP_OS_FAMILY,
            'osVersion' => php_uname('r'),
            'screenWidth' => 0,
            'screenHeight' => 0,
            'framework' => 'laravel',
            'sdkVersion' => self::SDK_VERSION,
            'environment' => $this->environment,
            'tags' => $this->buildTags($record),
            'breadcrumbs' => [],
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.v\Z'),
            'deviceType' => 'server',
        ];
    }

    public function fromMessage(LogRecord $record): array
    {
        return [
            'message' => $record->message,
            'type' => $record->level->name,
            'stack' => '',
            'source' => '',
            'line' => 0,
            'col' => 0,
            'fingerprint' => $this->fingerprint($record->level->name, $record->message, ''),
            'url' => $this->getUrl(),
            'userAgent' => 'Laravel/'.app()->version(),
            'browser' => 'server',
            'browserVersion' => '',
            'os' => PHP_OS_FAMILY,
            'osVersion' => php_uname('r'),
            'screenWidth' => 0,
            'screenHeight' => 0,
            'framework' => 'laravel',
            'sdkVersion' => self::SDK_VERSION,
            'environment' => $this->environment,
            'tags' => $this->buildTags($record),
            'breadcrumbs' => [],
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.v\Z'),
            'deviceType' => 'server',
        ];
    }

    private function fingerprint(string $type, string $message, string $frame): string
    {
        return md5($type.$message.$frame);
    }

    private function getFirstFrame(Throwable $e): string
    {
        $trace = $e->getTrace();

        if (empty($trace)) {
            return $e->getFile().':'.$e->getLine();
        }

        $frame = $trace[0];

        return ($frame['file'] ?? '').':'.($frame['line'] ?? 0);
    }

    private function getUrl(): string
    {
        if (app()->runningInConsole()) {
            return 'artisan://'.implode(' ', $_SERVER['argv'] ?? ['unknown']);
        }

        try {
            return request()->fullUrl();
        } catch (Throwable) {
            return '';
        }
    }

    private function buildTags(LogRecord $record): array
    {
        $tags = [
            'site_id' => $this->siteId,
            'php_version' => PHP_VERSION,
            'hostname' => gethostname() ?: 'unknown',
            'log_channel' => $record->channel,
        ];

        if (isset($record->context['tags']) && is_array($record->context['tags'])) {
            $tags = array_merge($tags, $record->context['tags']);
        }

        return $tags;
    }
}
