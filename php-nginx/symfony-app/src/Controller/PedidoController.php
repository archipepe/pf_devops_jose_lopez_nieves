<?php

namespace App\Controller;

use App\Service\PedidoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PedidoController extends AbstractController
{
    private PedidoService $pedidoService;

    public function __construct(
        PedidoService $pedidoService
    )
    {
        $this->pedidoService = $pedidoService;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $usuario = $this->getUser();
        
        // Obtener filtro de estado
        $estado = $request->query->get('estado', 'todos');
        
        // Obtener pedidos
        $pedidos = $this->pedidoService->getPedidosPorEstado($usuario, $estado);
        
        // Obtener estadísticas
        $estadisticas = $this->pedidoService->getEstadisticas($usuario);
        
        // Estados para el filtro
        $estadosDisponibles = $this->pedidoService->getEstadosDisponibles();
        
        return $this->render('pedido/index.html.twig', [
            'pedidos' => $pedidos,
            'estadisticas' => $estadisticas,
            'estadoActual' => $estado,
            'estadosDisponibles' => $estadosDisponibles
        ]);
    }

    /**
     * @param integer $id
     * @return Response
     */
    public function show(int $id): Response
    {
        $usuario = $this->getUser();
        
        $pedido = $this->pedidoService->getPedidoDetalle($id, $usuario);
        
        if (!$pedido) {
            throw $this->createNotFoundException('Pedido no encontrado');
        }
        
        // Formatear datos para la vista
        $pedidoFormateado = $this->pedidoService->formatearPedidoParaVista($pedido);
        
        return $this->render('pedido/pedido.html.twig', [
            'pedido' => $pedido,
            'pedidoFormateado' => $pedidoFormateado,
            'lineas' => $pedido->getLineas()
        ]);
    }
}
