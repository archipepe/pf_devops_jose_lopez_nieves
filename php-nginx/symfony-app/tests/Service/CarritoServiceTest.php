<?php

namespace App\Tests\Service;

use App\Entity\Carrito;
use App\Repository\CarritoRepository;
use App\Repository\ProductoCarritoRepository;
use App\Service\CarritoService;
use App\Service\MonitoringService;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CarritoServiceTest extends TestCase
{
    private CarritoService $carritoService;
    private TokenStorageInterface $tokenStorage;
    private CarritoRepository $carritoRepository;
    private ProductoCarritoRepository $productoCarritoRepository;
    private MonitoringService $monitoringService;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->carritoRepository = $this->createMock(CarritoRepository::class);
        $this->productoCarritoRepository = $this->createMock(ProductoCarritoRepository::class);
        $monitoringServiceMock = $this->createMock(MonitoringService::class);
        $monitoringServiceMock
            ->method('getLogger')
            ->willReturn($this->createMock(LoggerInterface::class));
        
        $monitoringServiceMock
            ->method('getTracerProvider')
            ->willReturn($this->createMock(TracerProviderInterface::class));

        $this->monitoringService = $monitoringServiceMock;
        
        $this->carritoService = new CarritoService(
            $this->tokenStorage,
            $this->carritoRepository,
            $this->productoCarritoRepository,
            $this->monitoringService
        );
    }

    public function testAddProductoAlCarrito(): void
    {
        // Arrange
        $hash = 'hash-123';
        $productoId = 1;
        $cantidad = 2;
        
        $carrito = new Carrito();
        $carrito->setHash($hash);
        
        $this->productoCarritoRepository
            ->expects($this->once())
            ->method('addProductoCarrito')
            ->with($carrito, $productoId, $cantidad);
        
        // Act
        $this->carritoService->addProducto($carrito, $productoId, $cantidad);
        
        // Assert
        $this->assertTrue(true);
    }
}
