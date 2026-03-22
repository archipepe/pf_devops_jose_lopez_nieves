<?php

namespace App\Service;

use App\Entity\Producto;
use App\Repository\ProductoRepository;
use Psr\Log\LoggerInterface;

class ProductoService
{
    private ProductoRepository $productoRepository;
    private LoggerInterface $logger;
    
    public function __construct(
        ProductoRepository $productoRepository,
        LoggerInterface $loggerInterface
    ) {
        $this->productoRepository = $productoRepository;
        $this->logger = $loggerInterface;
    }

    /**
     * @param integer $limit
     * @return Producto[]
     */
    public function obtenerProductosDestacados(int $limit) : array
    {
        return $this->productoRepository->findDestacados($limit);
    }
    
    /**
     * @return Producto[]
     */
    public function obtenerTodos() : array
    {
        return $this->productoRepository->findAll();
    }

    /**
     * @param integer $idProducto
     * @return Producto|null
     */
    public function obtenerProductoPorId(int $idProducto) : ?Producto
    {
        // TODO: si generas con AppFixtures, asegúrate de que al menos tres productos tengan los IDs 4, 5 y 6 para que se puedan probar los tiempos de espera simulados.
        $this->recuperarProducto($idProducto);

        $this->actualizarMetadata($idProducto);

        $this->enviarFacebookPixel($idProducto);

        return $this->productoRepository->findOneBy(['id' => $idProducto]);
    }

    /**
     * Simulación de recuperación de producto para logs, métricas y trazas.
     *
     * @param integer $idProducto
     * @return array|null
     */
    private function recuperarProducto(int $idProducto): ?array
    {
        if ($idProducto === 4) {
            $tiempoEspera = rand(3, 5);
            sleep($tiempoEspera);
            # Registrarlo en el monolog
            $this->logger->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        } else {
            $tiempoEspera = rand(0.5, 1);
            $this->logger->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        }

        return self::$productos[$idProducto] ?? null;
    }

    /**
     * Simulación de actualización de metadata de producto para logs, métricas y trazas.
     *
     * @param integer $idProducto
     * @return void
     */
    private function actualizarMetadata(int $idProducto): void
    {
        if ($idProducto === 5) {
            $tiempoEspera = rand(3, 5);
            sleep($tiempoEspera);
            # Registrarlo en el monolog
            $this->logger->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        } else {
            $tiempoEspera = rand(0.5, 1);
            $this->logger->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        }
    }

    /**
     * Simulación de envío a Facebook Pixel para logs, métricas y trazas.
     *
     * @param integer $idProducto
     * @return void
     */
    private function enviarFacebookPixel(int $idProducto): void
    {
        if ($idProducto === 6) {
            $tiempoEspera = rand(3, 5);
            sleep($tiempoEspera);
            # Registrarlo en el monolog
            $this->logger->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        } else {
            $tiempoEspera = rand(0.5, 1);
            $this->logger->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        }
    }
}
