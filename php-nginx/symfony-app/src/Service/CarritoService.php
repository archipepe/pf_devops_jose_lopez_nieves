<?php

namespace App\Service;

use App\Entity\Carrito;
use App\Entity\ProductoCarrito;
use App\Entity\User;
use App\Repository\CarritoRepository;
use App\Repository\EstadoCarritoRepository;
use App\Repository\ProductoRepository;
use App\Repository\ProductoCarritoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CarritoService
{
    private TokenStorageInterface $tokenStorage;
    private EntityManagerInterface $entityManager;
    private CarritoRepository $carritoRepository;
    private ProductoRepository $productoRepository;
    private ProductoCarritoRepository $productoCarritoRepository;
    private EstadoCarritoRepository $estadoCarritoRepository;
    
    public function __construct(
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager,
        CarritoRepository $carritoRepository,
        ProductoRepository $productoRepository,
        ProductoCarritoRepository $productoCarritoRepository,
        EstadoCarritoRepository $estadoCarritoRepository
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
        $this->carritoRepository = $carritoRepository;
        $this->productoRepository = $productoRepository;
        $this->productoCarritoRepository = $productoCarritoRepository;
        $this->estadoCarritoRepository = $estadoCarritoRepository;
    }

    /**
     * Obtiene o crea el carrito actual basado en la sesión/usuario.
     * Prevalece el carrito de un usuario.
     *
     * @param string $hash
     * @return Carrito
     */
    public function getCarritoActual(string $hash): Carrito
    {
        $user = $this->getUser();

        if ($user) {
            $carrito = $this->getCarritoActualByUser();
        } else {
            $carrito = $this->getCarritoActualByHash($hash);
        }
        
        return $carrito;
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
        $producto = $this->productoRepository->find($productoId);
        if (!$producto) {
            throw new \Exception('Producto no encontrado');
        }

        // Buscar si el producto ya está en el carrito
        $productoCarrito = null;
        foreach ($carrito->getProductos() as $item) {
            if ($item->getProducto()->getId() === $productoId) {
                $productoCarrito = $item;
                break;
            }
        }
        
        if ($productoCarrito) {
            // Actualizar cantidad
            $nuevaCantidad = $productoCarrito->getCantidad() + $cantidad;
            $productoCarrito->setCantidad($nuevaCantidad);
        } else {
            // Crear nuevo item
            $productoCarrito = new ProductoCarrito();
            $productoCarrito->setCarrito($carrito);
            $productoCarrito->setProducto($producto);
            $productoCarrito->setCantidad($cantidad);
            $this->entityManager->persist($productoCarrito);

            $carrito->addProducto($productoCarrito);
        }
        
        $carrito->setActualizadoEn(new \DateTimeImmutable());
        $this->entityManager->flush();
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
        $productoCarrito = $this->productoCarritoRepository->find($productoCarritoId);
            
        if (!$productoCarrito) {
            throw new \Exception('Producto no encontrado en el carrito');
        }
        
        if ($cantidad <= 0) {
            $this->removeProducto($productoCarritoId);
            return;
        }
        
        $productoCarrito->setCantidad($cantidad);
        $productoCarrito->getCarrito()->setActualizadoEn(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * Elimina un producto del carrito
     *
     * @param integer $productoCarritoId
     * @return void
     */
    public function removeProducto(int $productoCarritoId): void
    {
        $productoCarrito = $this->productoCarritoRepository->find($productoCarritoId);
            
        if ($productoCarrito) {
            $carrito = $productoCarrito->getCarrito();
            $carrito->removeProducto($productoCarrito);
            $carrito->setActualizadoEn(new \DateTimeImmutable());
            $this->entityManager->remove($productoCarrito);
            $this->entityManager->flush();
        }
    }

    /**
     * Vaciar el carrito.
     *
     * @param Carrito $carrito
     * @return void
     */
    public function vaciarCarrito(Carrito $carrito): void
    {
        foreach ($carrito->getProductos() as $productoCarrito) {
            $this->entityManager->remove($productoCarrito);
        }
        
        $carrito->setActualizadoEn(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * Fusiona el carrito anónimo con el del usuario al hacer login.
     *
     * @param Request $request
     * @return void
     */
    public function fusionarCarritoAlLogin(Request $request): void
    {
        $carritoHash = $request->attributes->get('carrito_hash');
        $carritoAnonimo = $this->getCarritoActualByHash($carritoHash);

        $carrito = $this->getCarritoActualByUser();

        $this->carritoRepository->fusionarCarritos($carritoAnonimo, $carrito);

        // Actualizar los valores en el request
        $request->attributes->set('carrito', $carrito);
        $request->attributes->set('carrito_hash', $carrito->getHash());
    }

    /**
     * Finaliza el carrito actual y crea uno nuevo
     */
    // public function finalizarCarrito(): Pedido
    // {
    //     $carritoActual = $this->getCarritoActual();
        
    //     // Verificar que no esté vacío
    //     if ($carritoActual->getProductos()->count() === 0) {
    //         throw new \Exception('No se puede finalizar un carrito vacío');
    //     }
        
    //     // Crear el pedido (lo implementaremos después)
    //     $pedido = $this->crearPedidoDesdeCarrito($carritoActual);
        
    //     // Marcar carrito como finalizado
    //     $carritoActual->setEstado('finalizado');
    //     $carritoActual->setPedido($pedido);
    //     $carritoActual->setActualizadoEn(new \DateTimeImmutable());
        
    //     // Crear NUEVO carrito activo para futuras compras
    //     $nuevoCarrito = $this->crearNuevoCarritoActivo($carritoActual);
        
    //     $this->entityManager->flush();
        
    //     // Actualizar el carrito actual en el servicio
    //     $this->carritoActual = $nuevoCarrito;
        
    //     return $pedido;
    // }

    // private function crearPedidoDesdeCarrito(Carrito $carrito): Pedido
    // {
    //     // Implementaremos esto en el próximo paso
    //     // Por ahora, solo un placeholder
    //     $pedido = new Pedido();
    //     $pedido->setUsuario($carrito->getUsuario());
    //     $pedido->setHash($carrito->getHash());
    //     $pedido->setTotal($carrito->getTotal());
    //     $pedido->setEstado('pendiente');
        
    //     $this->entityManager->persist($pedido);
        
    //     // Copiar productos al pedido
    //     foreach ($carrito->getProductos() as $productoCarrito) {
    //         $lineaPedido = new LineaPedido();
    //         $lineaPedido->setPedido($pedido);
    //         $lineaPedido->setProducto($productoCarrito->getProducto());
    //         $lineaPedido->setCantidad($productoCarrito->getCantidad());
    //         $lineaPedido->setPrecioUnitario($productoCarrito->getProducto()->getPrecio());
            
    //         $this->entityManager->persist($lineaPedido);
    //     }
        
    //     return $pedido;
    // }

    // private function crearNuevoCarritoActivo(Carrito $carritoAnterior): Carrito
    // {
    //     $nuevoCarrito = new Carrito();
        
    //     if ($carritoAnterior->getUsuario()) {
    //         // Si el anterior tenía usuario, el nuevo también
    //         $nuevoCarrito->setUsuario($carritoAnterior->getUsuario());
    //     } else {
    //         // Si era anónimo, mantener la misma sesión
    //         $nuevoCarrito->setHash($carritoAnterior->getHash());
    //     }
        
    //     $nuevoCarrito->setEstado('activo');
        
    //     $this->entityManager->persist($nuevoCarrito);
        
    //     return $nuevoCarrito;
    // }

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
