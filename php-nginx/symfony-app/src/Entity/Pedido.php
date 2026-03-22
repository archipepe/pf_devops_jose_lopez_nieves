<?php

namespace App\Entity;

use App\Repository\PedidoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PedidoRepository::class)]
class Pedido
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pedidos')]
    private ?User $usuario = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $estado = null; // pendiente, pagado, enviado, cancelado

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $creadoEn = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pagadoEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referenciaPago = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $direccionEnvio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ciudad = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codigoPostal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pais = null;

    #[ORM\OneToMany(mappedBy: 'pedido', targetEntity: LineaPedido::class)]
    private Collection $lineas;

    public function __construct()
    {
        $this->creadoEn = new \DateTimeImmutable();
        $this->lineas = new ArrayCollection();
        $this->estado = 'pendiente';
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

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function setEstado(?string $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(?string $total): static
    {
        $this->total = $total;

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

    public function getPagadoEn(): ?\DateTimeImmutable
    {
        return $this->pagadoEn;
    }

    public function setPagadoEn(?\DateTimeImmutable $pagadoEn): static
    {
        $this->pagadoEn = $pagadoEn;

        return $this;
    }

    public function getReferenciaPago(): ?string
    {
        return $this->referenciaPago;
    }

    public function setReferenciaPago(?string $referenciaPago): static
    {
        $this->referenciaPago = $referenciaPago;

        return $this;
    }

    public function getDireccionEnvio(): ?string
    {
        return $this->direccionEnvio;
    }

    public function setDireccionEnvio(?string $direccionEnvio): static
    {
        $this->direccionEnvio = $direccionEnvio;

        return $this;
    }

    public function getCiudad(): ?string
    {
        return $this->ciudad;
    }

    public function setCiudad(?string $ciudad): static
    {
        $this->ciudad = $ciudad;

        return $this;
    }

    public function getCodigoPostal(): ?string
    {
        return $this->codigoPostal;
    }

    public function setCodigoPostal(?string $codigoPostal): static
    {
        $this->codigoPostal = $codigoPostal;

        return $this;
    }

    public function getPais(): ?string
    {
        return $this->pais;
    }

    public function setPais(?string $pais): static
    {
        $this->pais = $pais;

        return $this;
    }

    /**
     * @return Collection<int, LineaPedido>
     */
    public function getLineas(): Collection
    {
        return $this->lineas;
    }

    public function addLinea(LineaPedido $linea): static
    {
        if (!$this->lineas->contains($linea)) {
            $this->lineas->add($linea);
            $linea->setPedido($this);
        }

        return $this;
    }

    public function removeLinea(LineaPedido $linea): static
    {
        if ($this->lineas->removeElement($linea)) {
            // set the owning side to null (unless already changed)
            if ($linea->getPedido() === $this) {
                $linea->setPedido(null);
            }
        }

        return $this;
    }

    public function getNumeroLineas(): int
    {
        return $this->lineas->count();
    }

    public function getTotalProductos(): int
    {
        $total = 0;
        foreach ($this->lineas as $linea) {
            $total += $linea->getCantidad();
        }
        return $total;
    }
}
