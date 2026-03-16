<?php

namespace App\Repository;

use App\Entity\EstadoCarrito;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EstadoCarrito>
 *
 * @method EstadoCarrito|null find($id, $lockMode = null, $lockVersion = null)
 * @method EstadoCarrito|null findOneBy(array $criteria, array $orderBy = null)
 * @method EstadoCarrito[]    findAll()
 * @method EstadoCarrito[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EstadoCarritoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EstadoCarrito::class);
    }

    public function findOneByControl(string $control): ?EstadoCarrito
    {
        return $this->findOneBy(['control' => $control]);
    }

//    /**
//     * @return EstadoCarrito[] Returns an array of EstadoCarrito objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?EstadoCarrito
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
