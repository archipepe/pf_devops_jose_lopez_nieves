<?php

namespace App\Entity;

use App\Repository\LineaPedidoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LineaPedidoRepository::class)]
class LineaPedido
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lineas')]
    private ?Pedido $pedido = null;

    #[ORM\ManyToOne]
    private ?Producto $producto = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombreProducto = null;

    #[ORM\Column(nullable: true)]
    private ?int $cantidad = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $precioUnitario = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $subtotal = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPedido(): ?Pedido
    {
        return $this->pedido;
    }

    public function setPedido(?Pedido $pedido): static
    {
        $this->pedido = $pedido;

        return $this;
    }

    public function getProducto(): ?Producto
    {
        return $this->producto;
    }

    public function setProducto(?Producto $producto): static
    {
        $this->producto = $producto;
        // Guardamos una copia de los datos actuales
        $this->nombreProducto = $producto->getNombre();
        $this->precioUnitario = $producto->getPrecio();

        return $this;
    }

    public function getNombreProducto(): ?string
    {
        return $this->nombreProducto;
    }

    public function setNombreProducto(?string $nombreProducto): static
    {
        $this->nombreProducto = $nombreProducto;

        return $this;
    }

    public function getCantidad(): ?int
    {
        return $this->cantidad;
    }

    public function setCantidad(?int $cantidad): static
    {
        $this->cantidad = $cantidad;
        $this->calcularSubtotal();

        return $this;
    }

    public function getPrecioUnitario(): ?string
    {
        return $this->precioUnitario;
    }

    public function setPrecioUnitario(?string $precioUnitario): static
    {
        $this->precioUnitario = $precioUnitario;
        $this->calcularSubtotal();

        return $this;
    }

    public function getSubtotal(): ?string
    {
        return $this->subtotal;
    }

    private function calcularSubtotal(): self
    {
        if ($this->precioUnitario && $this->cantidad) {
            $this->subtotal = (string)($this->precioUnitario * $this->cantidad);
        }
        return $this;
    }
}
