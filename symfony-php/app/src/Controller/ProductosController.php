<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductosController extends AbstractController
{
    public static array $productos = [
        1 => ['id' => 1, 'nombre' => 'Zapatillas Running', 'precio' => 89.99, 'descripcion' => 'Zapatillas ideales para running con amortiguación de alta calidad.', 'imagen' => 'sneakers.jpg'],
        2 => ['id' => 2, 'nombre' => 'Camiseta Deportiva', 'precio' => 29.99, 'descripcion' => 'Camiseta transpirable para actividades deportivas.', 'imagen' => 't-shirt.jpg'],
        3 => ['id' => 3, 'nombre' => 'Riñonera', 'precio' => 19.99, 'descripcion' => 'Riñonera práctica con múltiples compartimentos.', 'imagen' => 'fanny-pack.jpg'],
    ];

    public function detalle(int $idProducto): Response
    {
        if (array_key_exists($idProducto, self::$productos)) {
            $producto = $this->recuperarProducto($idProducto);
    
            $this->actualizarMetadata($idProducto);
    
            $this->enviarFacebookPixel($idProducto);
        } else {
            throw $this->createNotFoundException('Producto no encontrado');
        }

        return $this->render('producto.html.twig', [
            'producto' => $producto
        ]);
    }

    private function recuperarProducto(int $idProducto): ?array
    {
        if ($idProducto === 1) {
            $tiempoEspera = rand(3, 5);
            sleep($tiempoEspera);
            # Registrarlo en el monolog
            // $this->get('logger')->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        } else {
            $tiempoEspera = rand(0.5, 1);
            // $this->get('logger')->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        }

        return self::$productos[$idProducto] ?? null;
    }

    private function actualizarMetadata(int $idProducto): void
    {
        if ($idProducto === 2) {
            $tiempoEspera = rand(3, 5);
            sleep($tiempoEspera);
            # Registrarlo en el monolog
            // $this->get('logger')->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        } else {
            $tiempoEspera = rand(0.5, 1);
            // $this->get('logger')->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        }
    }

    private function enviarFacebookPixel(int $idProducto): void
    {
        if ($idProducto === 3) {
            $tiempoEspera = rand(3, 5);
            sleep($tiempoEspera);
            # Registrarlo en el monolog
            // $this->get('logger')->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        } else {
            $tiempoEspera = rand(0.5, 1);
            // $this->get('logger')->info("Recuperado producto con ID $idProducto después de esperar $tiempoEspera segundos.");
        }
    }
}
