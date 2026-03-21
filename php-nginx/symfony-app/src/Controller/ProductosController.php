<?php

namespace App\Controller;

use App\Service\ProductoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ProductosController extends AbstractController
{
    private ProductoService $productoService;

    public function __construct(
        ProductoService $productoService
    )
    {
        $this->productoService = $productoService;
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
        $producto = $this->productoService->obtenerProductoPorId($idProducto);

        if (!$producto) {
            throw $this->createNotFoundException('Producto no encontrado');
        }
        
        return $this->render('producto/producto.html.twig', [
            'producto' => $producto
        ]);
    }
}
