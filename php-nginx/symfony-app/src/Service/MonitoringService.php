<?php

namespace App\Service;

use App\OpenTelemetry\Metrics\Metrics;
use App\OpenTelemetry\Tracing\Tracing;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Psr\Log\LoggerInterface;

class MonitoringService
{
    private TracerProviderInterface $tracerProvider;
    private LoggerInterface $logger;
    private MeterProviderInterface $meterProvider;

    public function __construct(
        Tracing $tracing,
        LoggerInterface $logger,
        Metrics $metrics
    ) {
        $this->tracerProvider = $tracing->getTracerProvider();
        $this->logger = $logger;
        $this->meterProvider = $metrics->getMeterProvider();
    }

    public function getTracerProvider() : TracerProviderInterface
    {
        return $this->tracerProvider;
    }

    public function getLogger() : LoggerInterface
    {
        return $this->logger;
    }

    public function getMeterProvider() : MeterProviderInterface
    {
        return $this->meterProvider;
    }
}
