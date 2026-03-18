<?php

namespace App\Controller;

use App\Service\CarritoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CarritoController extends AbstractController
{
    private CarritoService $carritoService;

    public function __construct(
        CarritoService $carritoService
    )
    {
        $this->carritoService = $carritoService;
    }

    public function index(Request $request): Response
    {
        // TODO
        $carrito = $request->attributes->get('carrito');
        
        return $this->render('carrito/index.html.twig', [
            'carrito' => $carrito,
            'total' => $carrito->getTotal(),
            'totalProductos' => $carrito->getTotalProductos()
        ]);
    }

    // public function add(int $id, Request $request): Response
    // {
    //     $cantidad = $request->request->get('cantidad', 1);
        
    //     try {
    //         $this->carritoService->addProducto($id, $cantidad);
    //         $this->addFlash('success', 'Producto añadido al carrito');
    //     } catch (\Exception $e) {
    //         $this->addFlash('error', 'Error al añadir el producto');
    //     }
        
    //     return $this->redirectToRoute('carrito_index');
    // }

    public function update(int $id, Request $request): Response
    {
        $cantidad = $request->request->get('cantidad', 1);
        
        try {
            $this->carritoService->updateCantidad($id, $cantidad);
            $this->addFlash('success', 'Carrito actualizado');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al actualizar el carrito');
        }
        
        return $this->redirectToRoute('app_carrito_index');
    }

    public function remove(int $id): Response
    {
        try {
            $this->carritoService->removeProducto($id);
            $this->addFlash('success', 'Producto eliminado del carrito');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al eliminar el producto');
        }
        
        return $this->redirectToRoute('app_carrito_index');
    }

    // public function clear(): Response
    // {
    //     $this->carritoService->vaciarCarrito();
    //     $this->addFlash('success', 'Carrito vaciado');
        
    //     return $this->redirectToRoute('app_carrito_index');
    // }
}
