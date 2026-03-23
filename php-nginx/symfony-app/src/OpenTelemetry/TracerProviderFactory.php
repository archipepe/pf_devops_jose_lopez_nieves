<?php

namespace App\OpenTelemetry;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Export\TransportInterface;

class TracerProviderFactory
{
    public static function create(): TracerProvider
    {
        $transport = (new OtlpHttpTransportFactory())->create(
            'http://otel-collector:4318/v1/traces',
            'application/json'
        );
        $exporter = new SpanExporter($transport);
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(
                Attributes::create([
                    'service.name' => 'symfony-app',
                    'service.version' => '1.0.0',
                    'deployment.environment' => $_ENV['APP_ENV'] ?? 'dev',
                ])
            )
        );
        return new TracerProvider(
            new SimpleSpanProcessor($exporter),
            null,
            $resource
        );
    }
}
