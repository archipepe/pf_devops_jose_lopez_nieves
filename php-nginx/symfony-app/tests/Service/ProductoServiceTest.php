<?php

namespace App\Tests\Service;

use App\Entity\Producto;
use App\Repository\ProductoRepository;
use App\Service\MonitoringService;
use App\Service\ProductoService;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProductoServiceTest extends TestCase
{
    private ProductoService $productoService;
    private ProductoRepository $productoRepository;
    private MonitoringService $monitoringService;

    protected function setUp(): void
    {
        // Crear mock del repository
        $this->productoRepository = $this->createMock(ProductoRepository::class);

        // Crear mock del MonitoringService
        $monitoringServiceMock = $this->createMock(MonitoringService::class);
        
        // Configurar mocks de MonitoringService
        $monitoringServiceMock
            ->method('getLogger')
            ->willReturn($this->createMock(LoggerInterface::class));
        
        $monitoringServiceMock
            ->method('getTracerProvider')
            ->willReturn($this->createMock(TracerProviderInterface::class));

        $this->monitoringService = $monitoringServiceMock;
        
        // Inyectar mocks en el servicio
        $this->productoService = new ProductoService($this->productoRepository, $this->monitoringService);
    }

    public function testObtenerProductoPorIdExitoso(): void
    {
        // Arrange
        $productoId = 1;
        $producto = $this->crearProducto($productoId, 'Zapatillas Test', '99.99');
        
        $this->productoRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $productoId])
            ->willReturn($producto);
        
        // Act
        $resultado = $this->productoService->obtenerProductoPorId($productoId);
        
        // Assert
        $this->assertSame($producto, $resultado);
        $this->assertEquals('Zapatillas Test', $resultado->getNombre());
    }

    public function testObtenerProductoPorIdNoEncontrado(): void
    {
        // Arrange
        $productoId = 999;
        
        $this->productoRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $productoId])
            ->willReturn(null);
        
        // Act
        $resultado = $this->productoService->obtenerProductoPorId($productoId);
        
        // Assert
        $this->assertSame(null, $resultado);        
    }

    public function testObtenerTodosLosProductos(): void
    {
        // Arrange
        $productos = [
            $this->crearProducto(1, 'Zapatillas', '99.99'),
            $this->crearProducto(2, 'Camiseta', '29.99'),
            $this->crearProducto(3, 'Pantalón', '49.99'),
        ];
        
        $this->productoRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($productos);
        
        // Act
        $resultado = $this->productoService->obtenerTodos();
        
        // Assert
        $this->assertCount(3, $resultado);
        $this->assertEquals('Zapatillas', $resultado[0]->getNombre());
    }

    private function crearProducto(int $id, string $nombre, string $precio): Producto
    {
        $producto = new Producto();
        $producto->setId($id);
        $producto->setNombre($nombre);
        $producto->setPrecio($precio);
        
        return $producto;
    }
}
