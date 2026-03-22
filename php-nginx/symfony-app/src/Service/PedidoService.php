<?php

namespace App\Service;

use App\Entity\Pedido;
use App\Entity\User;
use App\Repository\PedidoRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PedidoService
{
    private TokenStorageInterface $tokenStorage;
    private PedidoRepository $pedidoRepository;
    
    public function __construct(
        TokenStorageInterface $tokenStorage,
        PedidoRepository $pedidoRepository
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->pedidoRepository = $pedidoRepository;
    }

    /**
     * @param Pedido $pedido
     * @return void
     */
    public function establecerPedidoPagado(Pedido $pedido) : void
    {
        $this->pedidoRepository->establecerPedidoPagado($pedido);
    }

    /**
     * @param integer $id
     * @return Pedido
     */
    public function getPedidoConLineas(int $id) : Pedido
    {
        return $this->pedidoRepository->findPedidoConLineas($id, $this->getUser());
    }

    /**
     * Obtiene todos los pedidos del usuario.
     * 
     * @param User $usuario
     * @return array
     */
    public function getPedidosUsuario(User $usuario): array
    {
        return $this->pedidoRepository->findByUsuarioOrdered($usuario);
    }

    /**
     * Obtiene un pedido específico con sus líneas.
     *
     * @param integer $id
     * @param User $usuario
     * @return Pedido|null
     */
    public function getPedidoDetalle(int $id, User $usuario): ?Pedido
    {
        return $this->pedidoRepository->findPedidoConLineas($id, $usuario);
    }

    /**
     * Obtiene estadísticas de compras del usuario.
     *
     * @param User $usuario
     * @return array
     */
    public function getEstadisticas(User $usuario): array
    {
        $stats = $this->pedidoRepository->getEstadisticas($usuario);
        
        // Formatear números
        $stats['totalGastado'] = (float) $stats['totalGastado'];
        $stats['promedioPedido'] = (float) $stats['promedioPedido'];
        
        return $stats;
    }

    /**
     * Obtiene pedidos filtrados por estado.
     *
     * @param User $usuario
     * @param string|null $estado
     * @return array
     */
    public function getPedidosPorEstado(User $usuario, ?string $estado = null): array
    {
        if ($estado && $estado !== 'todos') {
            return $this->pedidoRepository->findByEstado($usuario, $estado);
        }
        
        return $this->getPedidosUsuario($usuario);
    }

    /**
     * Obtiene los estados disponibles para filtrado.
     *
     * @return array
     */
    public function getEstadosDisponibles(): array
    {
        return [
            'todos' => 'Todos',
            'pendiente' => 'Pendiente',
            'pagado' => 'Pagado',
            'enviado' => 'Enviado',
            'cancelado' => 'Cancelado'
        ];
    }

    /**
     * Formatea un pedido para la vista (datos adicionales).
     *
     * @param Pedido $pedido
     * @return array
     */
    public function formatearPedidoParaVista(Pedido $pedido): array
    {
        return [
            'id' => $pedido->getId(),
            'fecha' => $pedido->getCreadoEn(),
            'total' => $pedido->getTotal(),
            'estado' => $pedido->getEstado(),
            'estadoLabel' => $this->getEstadoLabel($pedido->getEstado()),
            'estadoClase' => $this->getEstadoClase($pedido->getEstado()),
            'numeroProductos' => $pedido->getTotalProductos(),
            'referencia' => $pedido->getReferenciaPago(),
            'direccion' => $pedido->getDireccionEnvio(),
            'ciudad' => $pedido->getCiudad(),
            'codigoPostal' => $pedido->getCodigoPostal(),
            'pais' => $pedido->getPais()
        ];
    }

    /**
     * @param string $estado
     * @return string
     */
    private function getEstadoLabel(string $estado): string
    {
        return match($estado) {
            'pendiente' => 'Pendiente de pago',
            'pagado' => 'Pagado',
            'enviado' => 'Enviado',
            'cancelado' => 'Cancelado',
            default => ucfirst($estado)
        };
    }

    /**
     * @param string $estado
     * @return string
     */
    private function getEstadoClase(string $estado): string
    {
        return match($estado) {
            'pendiente' => 'warning',
            'pagado' => 'success',
            'enviado' => 'info',
            'cancelado' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Obtiene el usuario actual si está autenticado
     *
     * @return User|null
     */
    private function getUser(): ?User
    {
        return $this->tokenStorage->getToken()?->getUser();
    }
}
