<?php

namespace App\Controller;

use App\Service\ProductoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends AbstractController
{
    private ProductoService $productoService;

    public function __construct(
        ProductoService $productoService
    )
    {
        $this->productoService = $productoService;
    }
    /**
     * Home del proyecto.
     * 
     * @return Response
     */
    public function index(): Response
    {
        $productosDestacados = $this->productoService->obtenerProductosDestacados(4);
    
        return $this->render('index.html.twig', [
            'productos' => $productosDestacados
        ]);
    }
}
