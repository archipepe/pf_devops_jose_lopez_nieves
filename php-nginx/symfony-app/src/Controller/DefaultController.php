<?php

namespace App\Controller;

use App\Service\ProductoService;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{
    private ProductoService $productoService;
    private TracerInterface $tracer;
    private LoggerInterface $logger;

    public function __construct(
        ProductoService $productoService,
        TracerInterface $tracerInterface,
        LoggerInterface $loggerInterface
    )
    {
        $this->productoService = $productoService;
        $this->tracer = $tracerInterface;
        $this->logger = $loggerInterface;
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
