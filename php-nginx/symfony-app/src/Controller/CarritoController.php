<?php

namespace App\Controller;

use App\Service\CarritoService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CarritoController extends AbstractController
{
    private CarritoService $carritoService;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private LoggerInterface $logger;

    public function __construct(
        CarritoService $carritoService,
        CsrfTokenManagerInterface $csrfTokenManager,
        LoggerInterface $logger
    )
    {
        $this->carritoService = $carritoService;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->logger = $logger;
    }

    /**
     * Resumen del carrito.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $carrito = $request->attributes->get('carrito');
        
        return $this->render('carrito/index.html.twig', [
            'carrito' => $carrito,
            'total' => $carrito->getTotal(),
            'totalProductos' => $carrito->getTotalProductos()
        ]);
    }

    /**
     * Añadir producto al carrito.
     *
     * @param integer $id
     * @param Request $request
     * @return Response
     */
    public function add(int $id, Request $request): Response
    {
        try {
            // Verificar CSRF
            $csrfToken = $request->headers->get('X-CSRF-TOKEN');
            if (!$csrfToken || !$this->csrfTokenManager->isTokenValid(new CsrfToken('carrito', $csrfToken))) {
                return $this->json(['error' => 'Token CSRF inválido'], Response::HTTP_FORBIDDEN);
            }

            // Verificar que es AJAX
            if (!$request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Solo se permiten peticiones AJAX'], Response::HTTP_BAD_REQUEST);
            }

            $cantidad = $request->request->getInt('cantidad', 1);
            
            // Validar cantidad
            if ($cantidad < 1 || $cantidad > 10) {
                return $this->json([
                    'error' => 'La cantidad debe estar entre 1 y 10'
                ], Response::HTTP_BAD_REQUEST);
            }

            $carrito = $request->attributes->get('carrito');

            // Añadir al carrito
            $this->carritoService->addProducto($carrito, $id, $cantidad);            
            
            // Log para métricas
            $this->logger->info('Producto añadido al carrito', [
                'producto_id' => $id,
                'cantidad' => $cantidad,
                'total_productos' => $carrito->getTotalProductos(),
                'usuario' => $this->getUser()?->getUserIdentifier(),
                'anonimo' => !$this->getUser()
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Producto añadido correctamente',
                'totalProductos' => $carrito->getTotalProductos(),
                'total' => $carrito->getTotal(),
                'productoId' => $id,
                'cantidad' => $cantidad
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al añadir producto al carrito', [
                'producto_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Error al procesar la solicitud'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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
