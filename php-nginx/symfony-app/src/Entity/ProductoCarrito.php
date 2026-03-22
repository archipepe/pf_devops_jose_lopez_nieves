<?php

namespace App\Entity;

use App\Repository\ProductoCarritoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductoCarritoRepository::class)]
class ProductoCarrito
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'productos')]
    private ?Carrito $carrito = null;

    #[ORM\ManyToOne]
    private ?Producto $producto = null;

    #[ORM\Column(nullable: true)]
    private ?int $cantidad = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCarrito(): ?Carrito
    {
        return $this->carrito;
    }

    public function setCarrito(?Carrito $carrito): static
    {
        $this->carrito = $carrito;

        return $this;
    }

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }

    public function setProducto(?Producto $producto): static
    {
        $this->producto = $producto;

        return $this;
    }

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(?int $cantidad): static
    {
        $this->cantidad = $cantidad;

        return $this;
    }

    public function sumarCantidad(?int $cantidad): static
    {
        $this->cantidad = $this->cantidad + $cantidad;

        return $this;
    }

    public function getSubtotal(): ?float
    {
        if ($this->producto === null || $this->cantidad === null) {
            return null;
        }

        return $this->producto->getPrecio() * $this->cantidad;
    }
}
