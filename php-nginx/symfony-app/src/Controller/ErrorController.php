<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
// use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class ErrorController extends AbstractController
{
    /**
     * Página de error forzado.
     *
     * @return Response
     */
    public function error(): Response
    {
        # En caso de que quieras denegar acceso por Controller en lugar de access_control
        // $this->denyAccessUnlessGranted('ROLE_USER');
        // o
        // if (!$this->isGranted('ROLE_USER')) {
        //     throw $this->createAccessDeniedException('No access for you!');
        // }
        // Son lo mismo escritos de forma diferente.
        // O añadir la siguiente anotación a la función:
        // @IsGranted("ROLE_USER") ← También se puede poner a nivel de clase
        // o:
        // #[IsGranted("ROLE_ADMIN")] ← PHP 8 Attributes

        throw new \Exception('Error simulado para probar la página de error');

        return $this->render('error.html.twig');
    }
}
