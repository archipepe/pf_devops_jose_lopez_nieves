<?php

namespace App\OpenTelemetry\Tracing;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerProviderInterface;

class Tracing
{
    private TracerProviderInterface $tracerProvider;

    public function __construct()
    {
        $this->tracerProvider = Globals::tracerProvider();
    }

    public function getTracerProvider() : TracerProviderInterface
    {
        return $this->tracerProvider;
    }
}
