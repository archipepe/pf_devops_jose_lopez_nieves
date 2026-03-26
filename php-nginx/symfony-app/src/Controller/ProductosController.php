<?php

namespace App\Controller;

use App\Entity\Producto;
use App\Service\MonitoringService;
use App\Service\ProductoService;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ProductosController extends AbstractController
{
    private ProductoService $productoService;
    private TracerInterface $tracer;
    private LoggerInterface $logger;
    private MeterInterface $metrics;
    private CounterInterface $counterProductos;
    private CounterInterface $counterProductosUsuarios;

    public function __construct(
        ProductoService $productoService,
        MonitoringService $monitoringService
    )
    {
        $this->productoService = $productoService;
        $this->tracer = $monitoringService->getTracerProvider()->getTracer(
            'ProductosController',
            '1.0.0'
        );
        $this->logger = $monitoringService->getLogger();
        $this->setMetrics($monitoringService);
    }

    /**
     * Listado de productos.
     *
     * @return Response
     */
    public function index(): Response
    {
        $productos = $this->productoService->obtenerTodos();
        
        return $this->render('producto/index.html.twig', [
            'productos' => $productos,
        ]);
    }

    /**
     * Detalle del producto.
     *
     * @param integer $idProducto
     * @return Response
     */
    public function detalle(int $idProducto): Response
    {
        $span = $this->tracer->spanBuilder('obtener_producto')->startSpan();
        $span->setAttribute('endpoint', '/productos/{idProducto}');
        $span->setAttribute('route', 'productos_detalle');

        $producto = $this->productoService->obtenerProductoPorId($idProducto);

        if (!$producto) {
            throw $this->createNotFoundException('Producto no encontrado');
        }

        $this->logAccess($producto);

        $this->incrementarProductos($producto);

        $this->incrementarProductosUsuarios($producto);

        $response = $this->render('producto/producto.html.twig', [
            'producto' => $producto
        ]);

        $span->setAttribute('http.response.status_code', $response->getStatusCode());
        $span->end();
        
        return $response;
    }

    /**
     * @param MonitoringService $monitoringService
     * @return void
     */
    private function setMetrics(MonitoringService $monitoringService) : void
    {
        $this->metrics = $monitoringService->getMeterProvider()->getMeter(
            'ProductosController',
            '1.0.0'
        );

        $this->createCounterProductos();

        $this->createCounterProductosUsuarios();
    }

    /**
     * @return void
     */
    private function createCounterProductos() : void
    {
        // Units de createCounter modifica el nombre de la variable, por eso lo dejamos a null
        $this->counterProductos = $this->metrics->createCounter(
            'solicitud_productos',
            null,
            'Número de veces que se solicita un producto.'
        );
    }

    /**
     * @return void
     */
    private function createCounterProductosUsuarios() : void
    {
        // Units de createCounter modifica el nombre de la variable, por eso lo dejamos a null
        $this->counterProductosUsuarios = $this->metrics->createCounter(
            'solicitud_productos_usuarios',
            null,
            'Número de veces que un usuario solicita un producto.'
        );
    }

    /**
     * @param Producto $producto
     * @return void
     */
    public function incrementarProductos(Producto $producto)
    {
        $this->counterProductos->add(
            1,
            [
                'producto.nombre' => $producto->getNombre()
            ]
        );
    }

    /**
     * @param Producto $producto
     * @return void
     */
    public function incrementarProductosUsuarios(Producto $producto)
    {
        $this->counterProductosUsuarios->add(
            1,
            [
                'producto.nombre' => $producto->getNombre(),
                'usuario' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'anónimo'
            ]
        );
    }

    /**
     * @param Producto $producto
     * @return void
     */
    private function logAccess(Producto $producto) : void
    {
        if ($this->getUser()) {
            $this->logger->warning($this->getUser()->getUserIdentifier() . ' está accediendo al producto ' . $producto->getNombre() . ' (id: ' . $producto->getId() . ').');
        } else {
            $this->logger->warning('Usuario anónimo está accediendo al producto ' . $producto->getNombre() . ' (id: ' . $producto->getId() . ').');
        }
    }
}
