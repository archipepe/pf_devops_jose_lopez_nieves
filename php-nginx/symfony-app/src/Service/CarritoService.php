<?php

namespace App\Service;

use App\Entity\Carrito;
use App\Entity\Pedido;
use App\Entity\User;
use App\Repository\CarritoRepository;
use App\Repository\ProductoCarritoRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CarritoService
{
    private TokenStorageInterface $tokenStorage;
    private CarritoRepository $carritoRepository;
    private ProductoCarritoRepository $productoCarritoRepository;
    
    public function __construct(
        TokenStorageInterface $tokenStorage,
        CarritoRepository $carritoRepository,
        ProductoCarritoRepository $productoCarritoRepository
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->carritoRepository = $carritoRepository;
        $this->productoCarritoRepository = $productoCarritoRepository;
    }

    /**
     * Guardar en request attributes para acceso fácil.
     *
     * @param Request $request
     * @param string|null $carritoHash
     * @return void
     */
    public function setCarritoHash(Request $request, ?string $carritoHash): void
    {
        $request->attributes->set('carrito_hash', $carritoHash);
    }

    /**
     * @param Request $request
     * @return string|null
     */
    public function getCarritoHash(Request $request): ?string
    {
        return $request->attributes->get('carrito_hash');
    }

    /**
     * @param Request $request
     * @param Carrito $carrito
     * @return void
     */
    public function setCarrito(Request $request, Carrito $carrito) : void
    {
        $request->attributes->set('carrito', $carrito);
    }

    /**
     * @param Request $request
     * @return Carrito
     */
    public function getCarrito(Request $request) : Carrito
    {
        return $request->attributes->get('carrito');
    }

    /**
     * Obtiene o crea el carrito actual basado en la sesión/usuario.
     * Prevalece el carrito de un usuario.
     *
     * @param Request $request
     * @param string|null $hash
     * @return void
     */
    public function setCarritoActual(Request $request, ?string $hash): void
    {
        $user = $this->getUser();

        if ($user) {
            $carrito = $this->getCarritoActualByUser();
        } else {
            $carrito = $this->getCarritoActualByHash($hash);
        }

        // Guardar carrito en request attributes
        $this->setCarritoCarritoHash($request, $carrito);
    }

    /**
     * Obtiene o crea el carrito actual basado en la sesión.
     *
     * @param string $hash
     * @return Carrito
     */
    public function getCarritoActualByHash(string $hash): Carrito
    {
        return $this->carritoRepository->findOrCreateByHash($hash);
    }

    /**
     * Obtiene o crea el carrito actual basado en el usuario autenticado.
     *
     * @return Carrito
     */
    public function getCarritoActualByUser(): Carrito
    {
        $user = $this->getUser();
        
        return $this->carritoRepository->findOrCreateByUser($user);
    }

    /**
     * Añade un producto al carrito
     *
     * @param Carrito $carrito
     * @param integer $productoId
     * @param integer $cantidad
     * @return void
     */
    public function addProducto(Carrito $carrito, int $productoId, int $cantidad = 1): void
    {
        $this->productoCarritoRepository->addProductoCarrito($carrito, $productoId, $cantidad);
    }

    /**
     * Actualiza la cantidad de un producto en el carrito
     *
     * @param integer $productoCarritoId
     * @param integer $cantidad
     * @return void
     */
    public function updateCantidad(int $productoCarritoId, int $cantidad): void
    {
        $this->productoCarritoRepository->updateCantidad($productoCarritoId, $cantidad);
    }

    /**
     * Elimina un producto del carrito
     *
     * @param integer $productoCarritoId
     * @return void
     */
    public function removeProducto(int $productoCarritoId): void
    {
        $this->productoCarritoRepository->removeProducto($productoCarritoId);
    }

    /**
     * Vaciar el carrito.
     *
     * @param Carrito $carrito
     * @return void
     */
    public function vaciarCarrito(Carrito $carrito): void
    {
        $this->productoCarritoRepository->removeAll($carrito);
    }

    /**
     * Fusiona el carrito anónimo con el del usuario al hacer login.
     *
     * @param Request $request
     * @return void
     */
    public function fusionarCarritoAlLogin(Request $request): void
    {
        $carritoHash = $this->getCarritoHash($request);
        $carritoAnonimo = $this->getCarritoActualByHash($carritoHash);

        $carrito = $this->getCarritoActualByUser();

        $this->carritoRepository->fusionarCarritos($carritoAnonimo, $carrito);

        // Actualizar los valores en el request
        $this->setCarritoCarritoHash($request, $carrito);
    }

    /**
     * Finaliza el carrito actual y crea uno nuevo.
     * 
     * @param Carrito $carrito
     * @param array $datosDireccion
     * @param Request $request
     * @return Pedido
     */
    public function finalizarCarrito(Carrito $carrito, array $datosDireccion, Request $request): Pedido
    {
        $pedido = $this->carritoRepository->finalizarCarrito($carrito, $datosDireccion, $this->getUser());

        // Crear NUEVO carrito activo para futuras compras
        $this->setCarritoActual($request, null);

        return $pedido;
    }

    /**
     * @param Request $request
     * @param Carrito $carrito
     * @return void
     */
    private function setCarritoCarritoHash(Request $request, Carrito $carrito) : void
    {
        $this->setCarrito($request, $carrito);
        $this->setCarritoHash($request, $carrito->getHash());
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
