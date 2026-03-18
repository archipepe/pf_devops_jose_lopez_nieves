<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    public function check(): Response
    {
        $forceHealthCheckError = false;

        if ($forceHealthCheckError) {
            throw new \Exception("Health check failed intentionally for testing.");
        }

        return $this->render('health/index.html.twig', [
            'controller_name' => 'HealthController',
        ]);
    }
}
