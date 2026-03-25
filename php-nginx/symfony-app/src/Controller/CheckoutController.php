<?php

namespace App\Controller;

use App\Service\CarritoService;
use App\Service\MonitoringService;
use App\Service\PedidoService;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\TracerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;

/**
 * @IsGranted("ROLE_USER")
 */
class CheckoutController extends AbstractController
{
    private CarritoService $carritoService;
    private PedidoService $pedidoService;
    private TracerInterface $tracer;
    private LoggerInterface $logger;

    public function __construct(
        CarritoService $carritoService,
        PedidoService $pedidoService,
        MonitoringService $monitoringService
    ) {
        $this->carritoService = $carritoService;
        $this->pedidoService = $pedidoService;
        $this->tracer = $monitoringService->getTracerProvider()->getTracer(
            'CheckoutController',
            '1.0.0'
        );
        $this->logger = $monitoringService->getLogger();
    }    

    /**
     * Página de checkout.
     *
     * @param Request $request
     * @return Response
     */
    public function checkout(Request $request): Response
    {
        $carrito = $this->carritoService->getCarrito($request);
        
        // Verificar que el carrito no está vacío
        if ($carrito->getProductos()->count() === 0) {
            $this->addFlash('error', 'Tu carrito está vacío');
            return $this->redirectToRoute('carrito_index');
        }

        // Guardar la URL del carrito para volver si es necesario
        $session = $request->getSession();
        $session->set('checkout_referer', $request->headers->get('referer'));

        return $this->render('checkout/index.html.twig', [
            'carrito' => $carrito,
            'total' => $carrito->getTotal()
        ]);
    }

    /**
     * Procesar el checkout y crear el pedido.
     *
     * @param Request $request
     * @return Response
     */
    public function procesar(Request $request): Response
    {
        $span = $this->tracer->spanBuilder('procesar_pago')->startSpan();
        $span->setAttribute('endpoint', '/checkout/procesar');
        $span->setAttribute('route', 'checkout_procesar');

        try {
            $carrito = $this->carritoService->getCarrito($request);
            
            if ($carrito->getProductos()->count() === 0) {
                throw new \Exception('El carrito está vacío');
            }

            $direccion = $request->request->get('direccion');
            $ciudad = $request->request->get('ciudad');
            $codigoPostal = $request->request->get('codigo_postal');
            $pais = $request->request->get('pais');

            if (!$direccion || !$ciudad || !$codigoPostal || !$pais) {
                throw new \Exception('Todos los campos de envío son obligatorios');
            }

            $pedido = $this->carritoService->finalizarCarrito(
                $carrito,
                [
                    'direccion' => $direccion,
                    'ciudad' => $ciudad,
                    'codigoPostal' => $codigoPostal,
                    'pais' => $pais
                ],
                $request);            

            // Simular pago (siempre exitoso para pruebas)
            $pagoExitoso = $this->simularPago($pedido);

            if (!$pagoExitoso) {
                throw new \Exception('Error en el procesamiento del pago');
            }

            // Marcar pedido como pagado
            $this->pedidoService->establecerPedidoPagado($pedido);

            // Log para métricas
            $this->logger->info('Pedido completado', [
                'pedido_id' => $pedido->getId(),
                'usuario' => $this->getUser()->getUserIdentifier(),
                'total' => $pedido->getTotal()
            ]);

            $this->addFlash('success', '¡Pedido realizado con éxito!');

            $redirectResponse = $this->redirectToRoute('pedido_confirmation', [
                'id' => $pedido->getId()
            ]);

            $span->setAttribute('http.response.status_code', $redirectResponse->getStatusCode());
        } catch (\Exception $e) {
            $this->logger->error('Error en checkout', [
                'error' => $e->getMessage()
            ]);
            
            $this->addFlash('error', $e->getMessage());

            $redirectResponse = $this->redirectToRoute('checkout');

            $span->setAttribute('http.response.status_code', $redirectResponse->getStatusCode());
        } finally {
            $span->end();
        }

        return $redirectResponse;
    }

    /**
     * Página de confirmación del pedido.
     *
     * @param integer $id
     * @return Response
     */
    public function confirmation(int $id): Response
    {
        $pedido = $this->pedidoService->getPedidoConLineas($id);

        if (!$pedido) {
            throw $this->createNotFoundException('Pedido no encontrado');
        }

        return $this->render('checkout/confirmacion.html.twig', [
            'pedido' => $pedido
        ]);
    }

    /**
     * Simular el proceso de pago (siempre satisfactorio para pruebas).
     * Usuario suzanne78@jast.com siempre tendrá un delay de entre 3 y 5 segundos para finalizar el carrito y generar pedido.
     *
     * @return boolean
     */
    private function simularPago(): bool
    {
        $parent = Span::getCurrent();
        $scope = $parent->activate();

        try {
            $span = $this->tracer->spanBuilder("simularPago")->startSpan();

            if (strcmp($this->getUser()->getUserIdentifier(), 'suzanne78@jast.com') === 0) {
                sleep(rand(3, 5));
            } else {
                usleep(rand(100000, 500000)); // 0.1 a 0.5 segundos
            }

            $span->end();
        } finally {
            $scope->detach();
        }
        
        return true;
    }
}
