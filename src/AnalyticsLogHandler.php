<?php

namespace Meemalabs\Analytics;

use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class AnalyticsLogHandler extends AbstractProcessingHandler
{
    // TODO: update once the production endpoint is finalized
    public const ENDPOINT = 'https://analytics.stacks.com';

    private ErrorReportBuilder $builder;

    private string $url;

    public function __construct(
        private string $token,
        string $siteId,
        string $environment,
        Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        $this->url = self::ENDPOINT.'/errors/collect';
        $this->builder = new ErrorReportBuilder($siteId, $environment);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $exception = $record->context['exception'] ?? null;

            $payload = $exception instanceof Throwable
                ? $this->builder->fromException($exception, $record)
                : $this->builder->fromMessage($record);

            Http::withHeaders([
                'X-Analytics-Token' => $this->token,
                'Content-Type' => 'application/json',
            ])
                ->timeout(5)
                ->post($this->url, $payload);
        } catch (Throwable) {
            // Silently fail — logging must never crash the app.
        }
    }
}
