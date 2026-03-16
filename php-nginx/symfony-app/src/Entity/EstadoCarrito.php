<?php

namespace App\Entity;

use App\Repository\EstadoCarritoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EstadoCarritoRepository::class)]
class EstadoCarrito
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $control = null;

    public const ACTIVO = 'activo';
    public const FUSIONADO = 'fusionado';
    public const FINALIZADO = 'finalizado';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getControl(): ?string
    {
        return $this->control;
    }

    public function setControl(string $control): static
    {
        $this->control = $control;

        return $this;
    }
}
