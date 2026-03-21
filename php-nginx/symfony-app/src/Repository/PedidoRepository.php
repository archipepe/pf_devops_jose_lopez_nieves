<?php

namespace App\Repository;

use App\Entity\Carrito;
use App\Entity\LineaPedido;
use App\Entity\Pedido;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends ServiceEntityRepository<Pedido>
 *
 * @method Pedido|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pedido|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pedido[]    findAll()
 * @method Pedido[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PedidoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pedido::class);
    }

    /**
     * Obtiene los pedidos de un usuario ordenados por fecha de creación descendente.
     *
     * @param User $usuario
     * @return Pedido[]
     */
    public function findByUsuarioOrdered(User $usuario): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('p.creadoEn', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene un pedido por su ID asegurándose de que pertenece al usuario dado, incluyendo sus líneas de pedido.
     *
     * @param integer $id
     * @param User $usuario
     * @return Pedido|null
     */
    public function findPedidoConLineas(int $id, User $usuario): ?Pedido
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.lineas', 'l')
            ->addSelect('l')
            ->where('p.id = :id')
            ->andWhere('p.usuario = :usuario')
            ->setParameter('id', $id)
            ->setParameter('usuario', $usuario)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Obtiene estadísticas de pedidos para un usuario.
     * 
     * @param User $usuario
     * @return array
     */
    public function getEstadisticas(User $usuario): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select(
                'COUNT(p.id) as totalPedidos',
                'SUM(p.total) as totalGastado',
                'AVG(p.total) as promedioPedido',
                'MAX(p.creadoEn) as ultimoPedido'
            )
            ->where('p.usuario = :usuario')
            ->andWhere('p.estado = :estado')
            ->setParameter('usuario', $usuario)
            ->setParameter('estado', 'pagado')
            ->getQuery();

        return $qb->getOneOrNullResult() ?? [
            'totalPedidos' => 0,
            'totalGastado' => 0,
            'promedioPedido' => 0,
            'ultimoPedido' => null
        ];
    }

    /**
     * Obtener pedidos por estado.
     * 
     * @param User $usuario
     * @param string $estado
     * @return array
     */
    public function findByEstado(User $usuario, string $estado): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.usuario = :usuario')
            ->andWhere('p.estado = :estado')
            ->setParameter('usuario', $usuario)
            ->setParameter('estado', $estado)
            ->orderBy('p.creadoEn', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param Request $request
     * @param Carrito $carrito
     * @param User $user
     * @return Pedido
     */
    public function crearPedidoDesdeCarrito(Carrito $carrito, array $datosDireccion, User $user): Pedido
    {
        $entityManager = $this->getEntityManager();
        
        $direccion      = $datosDireccion['direccion'];
        $ciudad         = $datosDireccion['ciudad'];
        $codigoPostal   = $datosDireccion['codigoPostal'];
        $pais           = $datosDireccion['pais'];

        $pedido = new Pedido();
        $pedido->setUsuario($user);
        $pedido->setTotal($carrito->getTotal());
        $pedido->setDireccionEnvio($direccion);
        $pedido->setCiudad($ciudad);
        $pedido->setCodigoPostal($codigoPostal);
        $pedido->setPais($pais);

        foreach ($carrito->getProductos() as $item) {
            $linea = new LineaPedido();
            $linea->setProducto($item->getProducto());
            $linea->setCantidad($item->getCantidad());
            $linea->setNombreProducto($item->getProducto()->getNombre());
            $linea->setPrecioUnitario($item->getProducto()->getPrecio());
            
            $pedido->addLinea($linea);
            $entityManager->persist($linea);
        }

        $entityManager->persist($pedido);
        
        return $pedido;
    }

    /**
     * @param Pedido $pedido
     * @return void
     */
    public function establecerPedidoPagado(Pedido $pedido) : void
    {
        $pedido->setEstado('pagado');
        $pedido->setPagadoEn(new \DateTimeImmutable());
        $pedido->setReferenciaPago('SIM-' . uniqid());

        $this->getEntityManager()->flush();
    }
}
