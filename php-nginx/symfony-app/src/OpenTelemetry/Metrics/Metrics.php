<?php

namespace App\OpenTelemetry\Metrics;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\MeterProviderInterface;

class Metrics
{
    private MeterProviderInterface $meterProvider;

    public function __construct()
    {
        $this->meterProvider = Globals::meterProvider();
    }

    public function getMeterProvider(): MeterProviderInterface
    {
        return $this->meterProvider;
    }
}
