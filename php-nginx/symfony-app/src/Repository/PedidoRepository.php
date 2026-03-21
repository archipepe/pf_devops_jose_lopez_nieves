<?php

namespace App\Repository;

use App\Entity\Pedido;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
}
