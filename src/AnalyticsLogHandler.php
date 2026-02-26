<?php

namespace Meemalabs\Analytics;

use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class AnalyticsLogHandler extends AbstractProcessingHandler
{
    private const ENDPOINT = 'http://localhost:3001/errors/collect';

    public const DEFAULT_ENDPOINT = 'http://localhost:3001';

    private ErrorReportBuilder $builder;

    public function __construct(
        private string $token,
        string $siteId,
        string $environment,
        Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

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
                ->post(self::ENDPOINT, $payload);
        } catch (Throwable) {
            // Silently fail — logging must never crash the app.
        }
    }
}
