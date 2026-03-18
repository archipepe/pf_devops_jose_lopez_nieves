<?php

namespace App\Controller;

use App\Entity\Producto;
use App\Repository\ProductoRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductosController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Listado de productos.
     *
     * @param ProductoRepository $productoRepository
     * @return Response
     */
    public function index(ProductoRepository $productoRepository): Response
    {
        $productos = $productoRepository->findAll();
        
        return $this->render('producto/index.html.twig', [
            'productos' => $productos,
        ]);
    }

    /**
     * Detalle del producto.
     *
     * @param integer $idProducto
     * @param ProductoRepository $productoRepository
     * @return Response
     */
    public function detalle(int $idProducto, ProductoRepository $productoRepository): Response
    {
        $producto = $productoRepository->findOneBy(['id' => $idProducto]);

        if (!$producto) {
            throw $this->createNotFoundException('Producto no encontrado');
        }
        
        // TODO: si generas con AppFixtures, asegúrate de que al menos tres productos tengan los IDs 1, 2 y 3 para que se puedan probar los tiempos de espera simulados.
        $this->recuperarProducto($idProducto);

        $this->actualizarMetadata($idProducto);

        $this->enviarFacebookPixel($idProducto);
        
        return $this->render('producto/producto.html.twig', [
            'producto' => $producto
        ]);
    }

    /**
     * Simulación de recuperación de producto para logs, métricas y trazas.
     *
     * @param integer $idProducto
     * @return array|null
     */
    private function recuperarProducto(int $idProducto): ?array
    {
        if ($idProducto === 1) {
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
        if ($idProducto === 2) {
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
        if ($idProducto === 3) {
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
