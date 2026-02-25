<?php

namespace Meemalabs\Analytics\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Meemalabs\Analytics\AnalyticsLogHandler;
use Meemalabs\Analytics\ErrorReportBuilder;

class AnalyticsTestCommand extends Command
{
    protected $signature = 'analytics:test
                            {--type=exception : Type of sample to send (exception, error, warning)}
                            {--message= : Custom message to send}';

    protected $description = 'Send a sample error to ts-analytics to verify your integration';

    public function handle(): int
    {
        $token = config('analytics.token', '');
        $siteId = config('analytics.site_id', '');
        $endpoint = config('analytics.endpoint', AnalyticsLogHandler::DEFAULT_ENDPOINT);
        $environment = config('analytics.environment', 'production');
        $enabled = config('analytics.enabled', true);

        $this->components->info('Analytics Configuration');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Endpoint', $endpoint],
                ['Site ID', $siteId ?: '<not set>'],
                ['Token', $token ? substr($token, 0, 8).'...' : '<not set>'],
                ['Environment', $environment],
                ['Enabled', $enabled ? 'Yes' : 'No'],
            ],
        );

        if (! $enabled) {
            $this->components->error('Analytics is disabled. Set ANALYTICS_ENABLED=true in your .env');

            return self::FAILURE;
        }

        if (empty($token)) {
            $this->components->error('No API token configured. Set ANALYTICS_TOKEN in your .env');

            return self::FAILURE;
        }

        if (empty($siteId)) {
            $this->components->error('No site ID configured. Set ANALYTICS_SITE_ID in your .env');

            return self::FAILURE;
        }

        $type = $this->option('type');
        $customMessage = $this->option('message');
        $builder = new ErrorReportBuilder($siteId, $environment);
        $url = rtrim($endpoint, '/').'/errors/collect';

        $this->components->info("Sending sample {$type} to {$url}");

        $payload = $this->buildPayload($type, $customMessage, $builder);

        try {
            $response = Http::withHeaders([
                'X-Analytics-Token' => $token,
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                $this->components->info('Sample error sent successfully!');
                $this->line('');
                $this->line('  Message: '.$payload['message']);
                $this->line('  Type: '.$payload['type']);
                $this->line('  Response: '.$response->body());
                $this->line('');
                $this->components->info('Check your ts-analytics dashboard to see the error.');

                return self::SUCCESS;
            }

            $this->components->error("Server returned HTTP {$response->status()}");
            $this->line('  Response: '.$response->body());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->components->error('Failed to send: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function buildPayload(string $type, ?string $customMessage, ErrorReportBuilder $builder): array
    {
        $now = now()->format('Y-m-d\TH:i:s.v\Z');

        return match ($type) {
            'warning' => [
                'message' => $customMessage ?? 'This is a sample warning from analytics:test',
                'type' => 'Warning',
                'stack' => '',
                'source' => '',
                'line' => 0,
                'col' => 0,
                'fingerprint' => md5('Warning'.($customMessage ?? 'sample-warning')),
                'url' => 'artisan://analytics:test',
                'userAgent' => 'Laravel/'.app()->version(),
                'browser' => 'server',
                'browserVersion' => '',
                'os' => PHP_OS_FAMILY,
                'osVersion' => php_uname('r'),
                'screenWidth' => 0,
                'screenHeight' => 0,
                'framework' => 'laravel',
                'sdkVersion' => '0.1.0',
                'environment' => config('analytics.environment', 'production'),
                'tags' => [
                    'site_id' => config('analytics.site_id'),
                    'php_version' => PHP_VERSION,
                    'hostname' => gethostname() ?: 'unknown',
                    'log_channel' => 'analytics',
                    'sample' => 'true',
                ],
                'breadcrumbs' => [],
                'timestamp' => $now,
                'deviceType' => 'server',
            ],
            'error' => [
                'message' => $customMessage ?? 'This is a sample error from analytics:test',
                'type' => 'Error',
                'stack' => '',
                'source' => '',
                'line' => 0,
                'col' => 0,
                'fingerprint' => md5('Error'.($customMessage ?? 'sample-error')),
                'url' => 'artisan://analytics:test',
                'userAgent' => 'Laravel/'.app()->version(),
                'browser' => 'server',
                'browserVersion' => '',
                'os' => PHP_OS_FAMILY,
                'osVersion' => php_uname('r'),
                'screenWidth' => 0,
                'screenHeight' => 0,
                'framework' => 'laravel',
                'sdkVersion' => '0.1.0',
                'environment' => config('analytics.environment', 'production'),
                'tags' => [
                    'site_id' => config('analytics.site_id'),
                    'php_version' => PHP_VERSION,
                    'hostname' => gethostname() ?: 'unknown',
                    'log_channel' => 'analytics',
                    'sample' => 'true',
                ],
                'breadcrumbs' => [],
                'timestamp' => $now,
                'deviceType' => 'server',
            ],
            default => $this->buildExceptionPayload($customMessage, $now),
        };
    }

    private function buildExceptionPayload(?string $customMessage, string $now): array
    {
        $message = $customMessage ?? 'Sample RuntimeException from analytics:test command';

        try {
            throw new \RuntimeException($message);
        } catch (\RuntimeException $e) {
            return [
                'message' => $e->getMessage(),
                'type' => 'RuntimeException',
                'stack' => $e->getTraceAsString(),
                'source' => $e->getFile(),
                'line' => $e->getLine(),
                'col' => 0,
                'fingerprint' => md5('RuntimeException'.$e->getMessage().$e->getFile().':'.$e->getLine()),
                'url' => 'artisan://analytics:test',
                'userAgent' => 'Laravel/'.app()->version(),
                'browser' => 'server',
                'browserVersion' => '',
                'os' => PHP_OS_FAMILY,
                'osVersion' => php_uname('r'),
                'screenWidth' => 0,
                'screenHeight' => 0,
                'framework' => 'laravel',
                'sdkVersion' => '0.1.0',
                'environment' => config('analytics.environment', 'production'),
                'tags' => [
                    'site_id' => config('analytics.site_id'),
                    'php_version' => PHP_VERSION,
                    'hostname' => gethostname() ?: 'unknown',
                    'log_channel' => 'analytics',
                    'sample' => 'true',
                ],
                'breadcrumbs' => [],
                'timestamp' => $now,
                'deviceType' => 'server',
            ];
        }
    }
}
