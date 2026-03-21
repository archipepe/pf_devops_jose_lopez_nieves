<?php

namespace App\Repository;

use App\Entity\Carrito;
use App\Entity\Producto;
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
    private ProductoRepository $productoRepository;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductoCarrito::class);
        $this->productoRepository = $registry->getRepository(Producto::class);
    }

    /**
     * @param Carrito $carrito
     * @param integer $productoId
     * @param integer $cantidad
     * @return void
     */
    public function addProductoCarrito(Carrito $carrito, int $productoId, int $cantidad = 1) : void
    {
        $entityManager = $this->getEntityManager();

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
            $productoCarrito->sumarCantidad($cantidad);
        } else {
            // Crear nuevo item
            $productoCarrito = new ProductoCarrito();            
            $productoCarrito->setProducto($producto);
            $productoCarrito->setCantidad($cantidad);
            
            $carrito->addProducto($productoCarrito); // Implica $productoCarrito->setCarrito($carrito);

            $entityManager->persist($productoCarrito);
        }
        
        $carrito->setActualizadoEn(new \DateTimeImmutable());
        $entityManager->flush();
    }

    /**
     * @param integer $productoCarritoId
     * @param integer $cantidad
     * @return void
     */
    public function updateCantidad(int $productoCarritoId, int $cantidad): void
    {
        $productoCarrito = $this->find($productoCarritoId);
            
        if (!$productoCarrito) {
            throw new \Exception('Producto no encontrado en el carrito');
        }
        
        if ($cantidad <= 0) {
            $this->removeProducto($productoCarritoId);
            return;
        }
        
        $productoCarrito->setCantidad($cantidad);
        $productoCarrito->getCarrito()->setActualizadoEn(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * @param integer $productoCarritoId
     * @return void
     */
    public function removeProducto(int $productoCarritoId): void
    {
        $entityManager = $this->getEntityManager();

        $productoCarrito = $this->find($productoCarritoId);
            
        if ($productoCarrito) {
            $carrito = $productoCarrito->getCarrito();
            $carrito->removeProducto($productoCarrito);
            $carrito->setActualizadoEn(new \DateTimeImmutable());
            $entityManager->remove($productoCarrito);
            $entityManager->flush();
        }
    }

    /**
     * @param Carrito $carrito
     * @return void
     */
    public function removeAll(Carrito $carrito): void
    {
        $entityManager = $this->getEntityManager();

        foreach ($carrito->getProductos() as $productoCarrito) {
            $carrito->removeProducto($productoCarrito);
            $entityManager->remove($productoCarrito);
        }
        
        $carrito->setActualizadoEn(new \DateTimeImmutable());
        $entityManager->flush();
    }
}
