<?php

namespace App\Controller;

use App\Service\MonitoringService;
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
        MonitoringService $monitoringService
    )
    {
        $this->productoService = $productoService;
        $this->tracer = $monitoringService->getTracerProvider()->getTracer(
            'DefaultController',
            '1.0.0'
        );
        $this->logger = $monitoringService->getLogger();
    }

    /**
     * Home del proyecto.
     * 
     * @return Response
     */
    public function index(): Response
    {
        $span = $this->tracer->spanBuilder('obtener_productos_destacados')->startSpan();
        
        $this->logAccess();

        $numeroProductosDestacados = 4;
        $productosDestacados = $this->productoService->obtenerProductosDestacados($numeroProductosDestacados);

        $span->setAttribute('productosDestacadosSolicitados.count', $numeroProductosDestacados);
        $span->setAttribute('productosDestacadosRecibidos.count', count($productosDestacados));

        $span->end();
    
        return $this->render('index.html.twig', [
            'productos' => $productosDestacados
        ]);
    }

    /**
     * @return void
     */
    private function logAccess() : void
    {
        if ($this->getUser()) {
            $this->logger->warning($this->getUser()->getUserIdentifier() . ' está accediendo a la tienda.');
        } else {
            $this->logger->warning('Usuario anónimo está accediendo a la tienda.');
        }
    }
}
