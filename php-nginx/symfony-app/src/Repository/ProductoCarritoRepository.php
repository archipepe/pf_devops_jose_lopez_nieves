<?php

namespace App\Repository;

use App\Entity\ProductoCarrito;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductoCarrito>
 *
 * @method ProductoCarrito|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductoCarrito|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductoCarrito[]    findAll()
 * @method ProductoCarrito[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductoCarritoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductoCarrito::class);
    }

//    /**
//     * @return ProductoCarrito[] Returns an array of ProductoCarrito objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ProductoCarrito
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
