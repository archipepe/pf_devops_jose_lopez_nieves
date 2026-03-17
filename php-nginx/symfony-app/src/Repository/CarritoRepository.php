<?php

namespace App\Repository;

use App\Entity\Carrito;
use App\Entity\EstadoCarrito;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Carrito::class);
        $this->estadoCarritoRepository = $registry->getRepository("App\Entity\EstadoCarrito");
    }

    public function findCarritoAnonimo(string $sessionId) : ?Carrito
    {
        return $this->findOneBy(['sessionId' => $sessionId, 'usuario' => null, 'estado' => $this->estadoCarritoRepository->findOneByControl(EstadoCarrito::ACTIVO)]);
    }

    public function findOrCreateBySessionId(string $sessionId): Carrito
    {
        
        $carrito = $this->findCarritoAnonimo($sessionId);
        
        if (!$carrito) {
            $carrito = new Carrito();
            $carrito->setSessionId($sessionId);
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
            ->setParameter('estado', EstadoCarrito::ACTIVO)
            ->orderBy('c.actualizadoEn', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // TODO
    /**
     * Fusiona un carrito anónimo con el carrito de un usuario
     */
    public function fusionarCarritos(Carrito $carritoAnonimo, User $usuario): Carrito
    {
        $entityManager = $this->getEntityManager();
        
        // Buscar si el usuario ya tiene un carrito
        $carritoUsuario = $this->findCarritoActivo($usuario);
        
        if (!$carritoUsuario) {
            // Si no tiene carrito, convertir el anónimo en carrito de usuario
            $carritoAnonimo->setUsuario($usuario);
            $carritoAnonimo->setSessionId(null);
            $carritoAnonimo->setActualizadoEn(new \DateTimeImmutable());
            $entityManager->flush();
            
            return $carritoAnonimo;
        }
        
        // Si tiene carrito, fusionar productos
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
                // Mover el producto al carrito del usuario
                $productoAnonimo->setCarrito($carritoUsuario);
            }
        }
        
        // TODO: Cambiar el estado del carrito anónimo a fusionado
        // $entityManager->remove($carritoAnonimo);
        // $carritoUsuario->setActualizadoEn(new \DateTimeImmutable());
        // $entityManager->flush();
        
        return $carritoUsuario;
    }

//    /**
//     * @return Carrito[] Returns an array of Carrito objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Carrito
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
