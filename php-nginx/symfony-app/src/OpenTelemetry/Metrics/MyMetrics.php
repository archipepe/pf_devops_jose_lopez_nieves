<?php

namespace App\OpenTelemetry\Metrics;

use OpenTelemetry\API\Globals;

class MyMetrics
{
    private $counter;

    public function __construct()
    {
        $meter = Globals::meterProvider()->getMeter(
            'symfony-app', '1.0.0'
        );

        $this->counter = $meter->createCounter(
            'app.requests_total',
            'requests',
            'Número total de peticiones procesadas'
        );
    }

    public function increment()
    {
        $this->counter->add(1);
    }
}
