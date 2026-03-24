<?php

namespace App\Controller;

use App\OpenTelemetry\Metrics\MyMetrics;
use App\Service\ProductoService;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use OpenTelemetry\API\Globals;

class DefaultController extends AbstractController
{
    private ProductoService $productoService;
    private TracerInterface $tracer;
    private LoggerInterface $logger;
    private MyMetrics $metrics;

    public function __construct(
        ProductoService $productoService,
        LoggerInterface $loggerInterface,
        MyMetrics $metrics
    )
    {
        $this->productoService = $productoService;
        $this->tracer = Globals::tracerProvider()->getTracer(
            'symfony-app',
            '1.0.0'
        ); // TODO: meter más parámetros
        $this->logger = $loggerInterface;
        $this->metrics = $metrics;
    }
    /**
     * Home del proyecto.
     * 
     * @return Response
     */
    public function index(): Response
    {
        $span = $this->tracer->spanBuilder('demo-operation')->startSpan();
        $scope = $span->activate();

        $this->logger->info('Demo endpoint called', [
                'trace_id' => $span->getContext()->getTraceId(),
                'span_id' => $span->getContext()->getSpanId(),
            ]);

        $result = $this->processData();
        $span->addEvent('Data processed successfully');
        $span->setAttribute('result.count', count($result));
        $this->logger->info('Processing completed', [
            'item_count' => count($result),
        ]);

        $this->metrics->increment();

        $productosDestacados = $this->productoService->obtenerProductosDestacados(4);
    
        return $this->render('index.html.twig', [
            'productos' => $productosDestacados
        ]);
    }

    private function processData(): array
    {
        $span = $this->tracer->spanBuilder('process-data')->startSpan();
        $scope = $span->activate();
        try {
            $this->logger->debug('Starting data processing');
            sleep(1);
            $data = [
                'id' => 1,
                'name' => 'Sample Item',
                'timestamp' => time(),
            ];
            $this->logger->debug('Data processing complete');
            return $data;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}
