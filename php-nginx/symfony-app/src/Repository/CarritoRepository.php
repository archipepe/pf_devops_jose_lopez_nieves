<?php

namespace App\Repository;

use App\Entity\Carrito;
use App\Entity\EstadoCarrito;
use App\Entity\Pedido;
use App\Entity\ProductoCarrito;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends ServiceEntityRepository<Carrito>
 *
 * @method Carrito|null find($id, $lockMode = null, $lockVersion = null)
 * @method Carrito|null findOneBy(array $criteria, array $orderBy = null)
 * @method Carrito[]    findAll()
 * @method Carrito[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CarritoRepository extends ServiceEntityRepository
{
    private EstadoCarritoRepository $estadoCarritoRepository;
    private PedidoRepository $pedidoRepository;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Carrito::class);
        $this->estadoCarritoRepository = $registry->getRepository(EstadoCarrito::class);
        $this->pedidoRepository = $registry->getRepository(Pedido::class);
    }

    public function findOneByHash(string $hash): ?Carrito
    {
        return $this->findOneBy(['hash' => $hash]);
    }

    public function findCarritoAnonimo(string $hash) : ?Carrito
    {
        return $this->findOneBy(['hash' => $hash, 'usuario' => null, 'estado' => $this->estadoCarritoRepository->findOneByControl(EstadoCarrito::ACTIVO)]);
    }

    public function findOrCreateByHash(string $hash): Carrito
    {
        
        $carrito = $this->findCarritoAnonimo($hash);
        
        if (!$carrito) {
            $carrito = new Carrito();
            $carrito->setHash($hash);
            $carrito->setEstado($this->estadoCarritoRepository->findOneByControl(EstadoCarrito::ACTIVO));
            $this->getEntityManager()->persist($carrito);
            $this->getEntityManager()->flush();
        }
        
        return $carrito;
    }

    public function findOrCreateByUser(User $usuario): Carrito
    {
        $carrito = $this->findCarritoActivo($usuario);
        
        if (!$carrito) {
            $carrito = new Carrito();
            $carrito->setUsuario($usuario);
            $carrito->setEstado($this->estadoCarritoRepository->findOneByControl(EstadoCarrito::ACTIVO));
            $this->getEntityManager()->persist($carrito);
            $this->getEntityManager()->flush();
        }
        
        return $carrito;
    }

    public function findCarritoActivo(User $usuario): ?Carrito
    {
        return $this->createQueryBuilder('c')
            ->where('c.usuario = :usuario')
            ->andWhere('c.estado = :estado')
            ->setParameter('usuario', $usuario)
            ->setParameter('estado', $this->estadoCarritoRepository->findOneByControl(EstadoCarrito::ACTIVO))
            ->orderBy('c.actualizadoEn', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Fusiona un carrito anónimo con el carrito de un usuario.
     *
     * @param Carrito $carritoAnonimo
     * @param Carrito $carritoUsuario
     * @return void
     */
    public function fusionarCarritos(?Carrito $carritoAnonimo, Carrito $carritoUsuario): void
    {
        $entityManager = $this->getEntityManager();
        
        if (!$carritoAnonimo) {
            return; // No hay carrito anónimo, nada que fusionar
        }
        
        foreach ($carritoAnonimo->getProductos() as $productoAnonimo) {
            $productoExistente = null;
            
            // Buscar si el producto ya existe en el carrito del usuario
            foreach ($carritoUsuario->getProductos() as $productoUsuario) {
                if ($productoUsuario->getProducto()->getId() === $productoAnonimo->getProducto()->getId()) {
                    $productoExistente = $productoUsuario;
                    break;
                }
            }
            
            if ($productoExistente) {
                // Sumar cantidades
                $nuevaCantidad = $productoExistente->getCantidad() + $productoAnonimo->getCantidad();
                $productoExistente->setCantidad($nuevaCantidad);
            } else {
                // Para dejar el carritoAnonimo intacto, creamos un nuevo ProductoCarrito para el carritoUsuario
                $nuevoProductoCarrito = new ProductoCarrito();
                $nuevoProductoCarrito->setCarrito($carritoUsuario);
                $nuevoProductoCarrito->setProducto($productoAnonimo->getProducto());
                $nuevoProductoCarrito->setCantidad($productoAnonimo->getCantidad());
                $entityManager->persist($nuevoProductoCarrito);

                $carritoUsuario->addProducto($nuevoProductoCarrito);                
            }
        }

        // Cambiar estado del carrito anónimo a fusionado
        $carritoAnonimo->setEstado($this->estadoCarritoRepository->findOneByControl(EstadoCarrito::FUSIONADO));
        $carritoAnonimo->setActualizadoEn(new \DateTimeImmutable());
        $carritoUsuario->setActualizadoEn(new \DateTimeImmutable());
        $entityManager->flush();
    }

    /**
     * @param Request $request
     * @param Carrito $carrito
     * @param User $user
     * @return Pedido
     */
    public function finalizarCarrito(Carrito $carrito, array $datosDireccion, User $user): Pedido
    {
        // Verificar que no esté vacío
        if ($carrito->getProductos()->count() === 0) {
            throw new \Exception('No se puede finalizar un carrito vacío');
        }
        
        // Crear el pedido
        $pedido = $this->pedidoRepository->crearPedidoDesdeCarrito($carrito, $datosDireccion, $user);
        
        // Marcar carrito como finalizado
        $carrito->setEstado($this->estadoCarritoRepository->findOneByControl(EstadoCarrito::FINALIZADO));
        $carrito->setPedido($pedido);
        $carrito->setActualizadoEn(new \DateTimeImmutable());
        $carrito->setFinalizadoEn(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
        
        return $pedido;
    }
}
