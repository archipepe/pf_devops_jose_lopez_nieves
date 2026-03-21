<?php

namespace App\Service;

use App\Entity\Pedido;
use App\Entity\User;
use App\Repository\PedidoRepository;
use Symfony\Component\HttpFoundation\Request;
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
     * Obtiene el usuario actual si está autenticado
     *
     * @return User|null
     */
    private function getUser(): ?User
    {
        return $this->tokenStorage->getToken()?->getUser();
    }
}
