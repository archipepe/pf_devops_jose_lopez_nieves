<?php

namespace App\Controller;

use App\Repository\ProductoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    public function index(ProductoRepository $productoRepository): Response
    {
        $productosDestacados = $productoRepository->findDestacados(4);
    
        return $this->render('index.html.twig', [
            'productos' => $productosDestacados
        ]);
    }
}
