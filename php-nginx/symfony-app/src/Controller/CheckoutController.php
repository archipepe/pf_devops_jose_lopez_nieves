<?php

namespace App\Controller;

use App\Entity\LineaPedido;
use App\Entity\Pedido;
use App\Service\CarritoService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;

/**
 * @IsGranted("ROLE_USER")
 */
class CheckoutController extends AbstractController
{
    private CarritoService $carritoService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        CarritoService $carritoService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->carritoService = $carritoService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }    

    /**
     * Página de checkout.
     *
     * @param Request $request
     * @return Response
     */
    public function checkout(Request $request): Response
    {
        $carrito = $request->attributes->get('carrito');
        
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
        try {
            $carrito = $request->attributes->get('carrito');
            
            if ($carrito->getProductos()->count() === 0) {
                throw new \Exception('El carrito está vacío');
            }

            // Validar datos de envío
            $direccion = $request->request->get('direccion');
            $ciudad = $request->request->get('ciudad');
            $codigoPostal = $request->request->get('codigo_postal');
            $pais = $request->request->get('pais');

            if (!$direccion || !$ciudad || !$codigoPostal || !$pais) {
                throw new \Exception('Todos los campos de envío son obligatorios');
            }

            // Crear el pedido
            $pedido = $this->crearPedidoDesdeCarrito($carrito, [
                'direccion' => $direccion,
                'ciudad' => $ciudad,
                'codigoPostal' => $codigoPostal,
                'pais' => $pais
            ]);

            // Simular pago (siempre exitoso para pruebas)
            $pagoExitoso = $this->simularPago($pedido);

            if (!$pagoExitoso) {
                throw new \Exception('Error en el procesamiento del pago');
            }

            // Marcar pedido como pagado
            $pedido->setEstado('pagado');
            $pedido->setPagadoEn(new \DateTimeImmutable());
            $pedido->setReferenciaPago('SIM-' . uniqid());

            $this->entityManager->flush();

            // Vaciar el carrito (el servicio ya crea uno nuevo automáticamente)
            $this->carritoService->vaciarCarrito($carrito);

            // Log para métricas
            $this->logger->info('Pedido completado', [
                'pedido_id' => $pedido->getId(),
                'usuario' => $this->getUser()->getUserIdentifier(),
                'total' => $pedido->getTotal()
            ]);

            $this->addFlash('success', '¡Pedido realizado con éxito!');

            return $this->redirectToRoute('pedido_confirmation', [
                'id' => $pedido->getId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error en checkout', [
                'error' => $e->getMessage()
            ]);
            
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('checkout');
        }
    }

    /**
     * Crear un pedido a partir del carrito y los datos de envío.
     *
     * @param [type] $carrito
     * @param array $datosEnvio
     * @return Pedido
     */
    private function crearPedidoDesdeCarrito($carrito, array $datosEnvio): Pedido
    {
        $pedido = new Pedido();
        $pedido->setUsuario($this->getUser());
        $pedido->setTotal($carrito->getTotal());
        $pedido->setDireccionEnvio($datosEnvio['direccion']);
        $pedido->setCiudad($datosEnvio['ciudad']);
        $pedido->setCodigoPostal($datosEnvio['codigoPostal']);
        $pedido->setPais($datosEnvio['pais']);

        foreach ($carrito->getProductos() as $item) {
            $linea = new LineaPedido();
            $linea->setProducto($item->getProducto());
            $linea->setCantidad($item->getCantidad());
            $linea->setNombreProducto($item->getProducto()->getNombre());
            $linea->setPrecioUnitario($item->getProducto()->getPrecio());
            
            $pedido->addLinea($linea);
            $this->entityManager->persist($linea);
        }

        $this->entityManager->persist($pedido);
        
        return $pedido;
    }

    /**
     * Simular el proceso de pago (siempre exitoso para pruebas).
     *
     * @param Pedido $pedido
     * @return boolean
     */
    private function simularPago(Pedido $pedido): bool
    {
        // Simulación simple - siempre exitoso
        // Aquí podrías añadir una pequeña pausa para simular procesamiento
        usleep(rand(100000, 500000)); // 0.1 a 0.5 segundos
        
        return true;
    }

    /**
     * Página de confirmación del pedido.
     *
     * @param integer $id
     * @return Response
     */
    public function confirmation(int $id): Response
    {
        $pedido = $this->entityManager
            ->getRepository(Pedido::class)
            ->findPedidoConLineas($id, $this->getUser());

        if (!$pedido) {
            throw $this->createNotFoundException('Pedido no encontrado');
        }

        return $this->render('checkout/confirmacion.html.twig', [
            'pedido' => $pedido
        ]);
    }
}
