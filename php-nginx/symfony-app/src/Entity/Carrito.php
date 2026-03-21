<?php

namespace App\Entity;

use App\Repository\CarritoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarritoRepository::class)]
class Carrito
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?User $usuario = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $hash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $actualizadoEn = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finalizadoEn = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?EstadoCarrito $estado = null;

    #[ORM\OneToMany(mappedBy: 'carrito', targetEntity: ProductoCarrito::class)]
    private Collection $productos;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Pedido $pedido = null;
    
    public function __construct()
    {
        $this->creadoEn = new \DateTimeImmutable();
        $this->actualizadoEn = new \DateTimeImmutable();
        $this->productos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): ?User
    {
        return $this->usuario;
    }

    public function setUsuario(?User $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): static
    {
        $this->hash = $hash;

        return $this;
    }

    public function getCreadoEn(): ?\DateTimeImmutable
    {
        return $this->creadoEn;
    }

    public function setCreadoEn(?\DateTimeImmutable $creadoEn): static
    {
        $this->creadoEn = $creadoEn;

        return $this;
    }

    public function getActualizadoEn(): ?\DateTimeImmutable
    {
        return $this->actualizadoEn;
    }

    public function setActualizadoEn(?\DateTimeImmutable $actualizadoEn): static
    {
        $this->actualizadoEn = $actualizadoEn;

        return $this;
    }

    public function getFinalizadoEn(): ?\DateTimeImmutable
    {
        return $this->finalizadoEn;
    }

    public function setFinalizadoEn(?\DateTimeImmutable $finalizadoEn): static
    {
        $this->finalizadoEn = $finalizadoEn;

        return $this;
    }

    public function getEstado(): ?EstadoCarrito
    {
        return $this->estado;
    }

    public function setEstado(?EstadoCarrito $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    /**
     * @return Collection<int, ProductoCarrito>
     */
    public function getProductos(): Collection
    {
        return $this->productos;
    }

    public function addProducto(ProductoCarrito $producto): static
    {
        if (!$this->productos->contains($producto)) {
            $this->productos->add($producto);
            $producto->setCarrito($this);
        }

        return $this;
    }

    public function removeProducto(ProductoCarrito $producto): static
    {
        if ($this->productos->removeElement($producto)) {
            // set the owning side to null (unless already changed)
            if ($producto->getCarrito() === $this) {
                $producto->setCarrito(null);
            }
        }

        return $this;
    }

    public function getTotal(): float
    {
        $total = 0;
        foreach ($this->productos as $productoCarrito) {
            $total += $productoCarrito->getSubtotal();
        }
        return $total;
    }

    public function getTotalProductos(): int
    {
        $total = 0;
        foreach ($this->productos as $productoCarrito) {
            $total += $productoCarrito->getCantidad();
        }
        return $total;
    }

    public function esAnonimo(): bool
    {
        return $this->usuario === null;
    }

    public function estaActivo(): bool
    {
        return $this->estado->getControl() === 'activo';
    }

    public function estaFusionado(): bool
    {
        return $this->estado->getControl() === 'fusionado';
    }

    public function estaFinalizado(): bool
    {
        return $this->estado->getControl() === 'finalizado';
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
}
