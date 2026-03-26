<?php

namespace App\OpenTelemetry\Logging;

use OpenTelemetry\Contrib\Logs\Monolog\Handler;
use OpenTelemetry\API\Globals;

class LoggingOtelHandler extends Handler
{
    public function __construct()
    {
        parent::__construct(
            Globals::loggerProvider(),
            \Psr\Log\LogLevel::INFO
        );
    }
}
