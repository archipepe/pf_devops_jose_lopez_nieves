<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ErrorController extends AbstractController
{
    public function error(): Response
    {
        throw new \Exception('Error simulado para probar la página de error');

        return $this->render('error.html.twig');
    }
}
