<?php

namespace App\Service;

use App\Entity\Carrito;
use App\Entity\EstadoCarrito;
use App\Entity\ProductoCarrito;
use App\Entity\User;
use App\Repository\CarritoRepository;
use App\Repository\EstadoCarritoRepository;
use App\Repository\ProductoRepository;
use App\Repository\ProductoCarritoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class CarritoService
{
    private Security $security;
    private EntityManagerInterface $entityManager;
    private CarritoRepository $carritoRepository;
    private ProductoRepository $productoRepository;
    private ProductoCarritoRepository $productoCarritoRepository;
    private EstadoCarritoRepository $estadoCarritoRepository;
    
    public function __construct(
        Security $security,
        EntityManagerInterface $entityManager,
        CarritoRepository $carritoRepository,
        ProductoRepository $productoRepository,
        ProductoCarritoRepository $productoCarritoRepository,
        EstadoCarritoRepository $estadoCarritoRepository
    ) {
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->carritoRepository = $carritoRepository;
        $this->productoRepository = $productoRepository;
        $this->productoCarritoRepository = $productoCarritoRepository;
        $this->estadoCarritoRepository = $estadoCarritoRepository;
    }

    /**
     * Obtiene o crea el carrito actual basado en la sesión/usuario
     *
     * @param string|null $hash
     * @return Carrito
     */
    public function getCarritoActual(?string $hash): Carrito
    {
        $user = $this->getUser();

        if ($user) {
            $carrito = $this->carritoRepository->findCarritoActivo($user);
            
            if (!$carrito) {
                $nuevoCarrito = new Carrito();
                $nuevoCarrito->setUsuario($user);
                $nuevoCarrito->setEstado($this->estadoCarritoRepository->findOneByControl(EstadoCarrito::ACTIVO));
                $this->entityManager->persist($nuevoCarrito);
                $this->entityManager->flush();
                
                $carrito = $nuevoCarrito;
            }
        } else {
            $carrito = $this->carritoRepository->findOrCreateByHash($hash);
        }
        
        return $carrito;
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
     * Vacía el carrito actual
     */
    // public function vaciarCarrito(): void
    // {
    //     $carrito = $this->getCarritoActual();
        
    //     foreach ($carrito->getProductos() as $productoCarrito) {
    //         $this->entityManager->remove($productoCarrito);
    //     }
        
    //     $carrito->setActualizadoEn(new \DateTimeImmutable());
    //     $this->entityManager->flush();
    // }

    /**
     * Fusiona el carrito anónimo con el del usuario al hacer login
     */
    // public function fusionarCarritoAlLogin(User $usuario): void
    // {
    //     $hash = $this->session->getId();
        
    //     // Buscar carrito anónimo ACTIVO
    //     $carritoAnonimo = $this->carritoRepository->findCarritoAnonimo($hash);
        
    //     if ($carritoAnonimo && $carritoAnonimo->getProductos()->count() > 0) {
    //         // Buscar carrito ACTIVO del usuario
    //         $carritoUsuario = $this->carritoRepository->findCarritoActivo($usuario);
            
    //         if (!$carritoUsuario) {
    //             // Si el usuario no tiene carrito activo, convertir el anónimo
    //             $carritoAnonimo->setUsuario($usuario);
    //             $carritoAnonimo->setHash(null);
    //             // $this->carritoActual = $carritoAnonimo;
    //             $this->entityManager->flush();
    //         } else {
    //             // Si tiene, fusionar y eliminar el anónimo
    //             $carritoFusionado = $this->carritoRepository->fusionarCarritos($carritoAnonimo, $usuario);
    //             // $this->carritoActual = $carritoFusionado;
    //         }
            
    //     }
    // }

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
        return $this->security->getUser();
    }
}
