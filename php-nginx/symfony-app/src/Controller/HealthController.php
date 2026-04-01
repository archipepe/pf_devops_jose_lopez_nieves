<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class HealthController extends AbstractController
{
    private bool $forceHealthCheckError;

    public function __construct(ParameterBagInterface $parameterBag) {
        $this->forceHealthCheckError = $parameterBag->get('forceHealthCheckError');
    }
    /**
     * Comprobación de la salud del proyecto.
     *
     * @return Response
     */
    public function check(): Response
    {
        if ($this->forceHealthCheckError) {
            throw new \Exception("Health check failed intentionally for testing.");
        }

        return $this->render('health/index.html.twig', [
            'controller_name' => 'HealthController',
        ]);
    }
}
