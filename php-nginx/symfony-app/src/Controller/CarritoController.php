<?php

namespace App\Controller;

use App\Service\CarritoService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $carrito = $this->carritoService->getCarrito($request);
        
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
            $this->verificarCsrf($request);

            $this->verificarAjax($request);

            $cantidad = $request->request->getInt('cantidad', 1);
            
            // Validar cantidad
            if ($cantidad < 1 || $cantidad > 10) {
                return $this->json([
                    'error' => 'La cantidad debe estar entre 1 y 10'
                ], Response::HTTP_BAD_REQUEST);
            }

            $carrito = $this->carritoService->getCarrito($request);

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

    /**
     * Actualizar la cantidad de un producto del carrito.
     *
     * @param Request $request
     * @param integer $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $this->verificarCsrf($request);
            
            $this->verificarAjax($request);
            
            $cantidad = $request->request->getInt('cantidad', 1);
            
            if ($cantidad < 1 || $cantidad > 10) {
                return $this->json(['error' => 'Cantidad inválida'], Response::HTTP_BAD_REQUEST);
            }
            
            $this->carritoService->updateCantidad($id, $cantidad);
            $carrito = $this->carritoService->getCarrito($request);
            
            // Buscar el item actualizado para devolver su nuevo subtotal
            $itemActualizado = null;
            foreach ($carrito->getProductos() as $item) {
                if ($item->getId() === $id) {
                    $itemActualizado = $item;
                    break;
                }
            }
            
            return $this->json([
                'success' => true,
                'totalProductos' => $carrito->getTotalProductos(),
                'total' => $carrito->getTotal(),
                'nuevoSubtotal' => $itemActualizado ? number_format($itemActualizado->getSubtotal(), 2, ',', '.') : null
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error actualizando cantidad', [
                'item_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->json(['error' => 'Error al actualizar'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar un producto del carrito. Equivale a poner cantidad a 0.
     *
     * @param Request $request
     * @param integer $id
     * @return JsonResponse
     */
    public function remove(Request $request, int $id): JsonResponse
    {
        try {
            $this->verificarCsrf($request);
            
            $this->carritoService->removeProducto($id);
            $carrito = $this->carritoService->getCarrito($request);
            
            return $this->json([
                'success' => true,
                'totalProductos' => $carrito->getTotalProductos(),
                'total' => $carrito->getTotal()
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al eliminar'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vaciar el carrito.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $this->verificarCsrf($request);
            
            $carrito = $this->carritoService->getCarrito($request);
            $this->carritoService->vaciarCarrito($carrito);
            
            return $this->json([
                'success' => true,
                'totalProductos' => 0,
                'total' => 0
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al vaciar'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verificar CSRF token.
     *
     * @param Request $request
     * @return void
     */
    private function verificarCsrf(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN');
        if (!$token || !$this->csrfTokenManager->isTokenValid(new CsrfToken('carrito', $token))) {
            throw new \Exception('Token CSRF inválido');
        }
    }

    /**
     * Verificar que es una petición AJAX.
     *
     * @param Request $request
     * @return void
     */
    private function verificarAjax(Request $request) : void
    {
        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestException('Solo se permiten peticiones AJAX');
        }
    }
}
