<?php

namespace App\DataFixtures;

use App\Entity\EstadoCarrito;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Factory\UserFactory;
use App\Entity\Producto;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        UserFactory::createOne(['email' => 'abraca_admin@example.com']);
        UserFactory::createMany(10);

        $manager->flush();

        $productos = [
            [
                'nombre' => 'Zapatillas Running',
                'descripcion' => 'Zapatillas ideales para running con amortiguación de alta calidad.',
                'precio' => 89.99,
                'imagen' => 'sneakers.jpg',
                'createdAt' => new \DateTimeImmutable('2026-02-19')
            ],
            [
                'nombre' => 'Camiseta Deportiva',
                'descripcion' => 'Camiseta transpirable para actividades deportivas.',
                'precio' => 29.99,
                'imagen' => 't-shirt.jpg',
                'createdAt' => new \DateTimeImmutable('2026-02-18')
            ],
            [
                'nombre' => 'Riñonera',
                'descripcion' => 'Riñonera práctica con múltiples compartimentos.',
                'precio' => 19.99,
                'imagen' => 'fanny-pack.jpg',
                'createdAt' => new \DateTimeImmutable('2026-02-17')
            ],
            [
                'nombre' => 'Calcetines Técnicos (Pack 3)',
                'descripcion' => 'Calcetines acolchados con tecnología antiolor y refuerzos en zonas de impacto.',
                'precio' => 19.99,
                'imagen' => 'socks-pack.jpg',
                'createdAt' => new \DateTimeImmutable('2026-02-16')
            ],
            [
                'nombre' => 'Gorra Running UV',
                'descripcion' => 'Gorra transpirable con protección UV y banda antisuor. Ajuste regulable.',
                'precio' => 15.99,
                'imagen' => 'cap-running.jpg',
                'createdAt' => new \DateTimeImmutable('2026-02-15')
            ],
            [
                'nombre' => 'Mochila Hidratación',
                'descripcion' => 'Mochila ligera con bolsa de agua incluida. Ideal para trails y largas distancias.',
                'precio' => 45.99,
                'imagen' => 'backpack-water.jpg',
                'createdAt' => new \DateTimeImmutable('2026-02-14')
            ]
        ];

        foreach ($productos as $datos) {
            $producto = new Producto();
            $producto->setNombre($datos['nombre'])
                    ->setDescripcion($datos['descripcion'])
                    ->setPrecio($datos['precio'])
                    ->setImagen($datos['imagen'])
                    ->setCreatedAt($datos['createdAt']);
            
            $manager->persist($producto);
        }

        $estados = [EstadoCarrito::ACTIVO, EstadoCarrito::FUSIONADO, EstadoCarrito::FINALIZADO];

        foreach ($estados as $control) {
            $estado = new EstadoCarrito();
            $estado->setControl($control);
            $manager->persist($estado);
        }

        $manager->flush();
    }
}
