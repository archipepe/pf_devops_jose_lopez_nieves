<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Cookie;

class CarritoIdGenerator
{
    public const COOKIE_NAME = 'carrito_id';
    public const COOKIE_EXPIRY = 2592000; // 30 días en segundos
    private RequestStack $requestStack;
    
    public function __construct(
        RequestStack $requestStack
    ) {
        $this->requestStack = $requestStack;
    }

    /**
     * Obtiene carrito_id de la cookie o genera uno nuevo.
     *
     * @return string
     */
    public function getCarritoId(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        // $response = $this->requestStack->getCurrentResponse();
        
        // Buscar en cookie
        $carritoId = $request->cookies->get(self::COOKIE_NAME);
        
        // Si no existe, generar uno nuevo
        if (!$carritoId) {
            $carritoId = $this->generarIdUnico();
            
            // Guardar en cookie para futuras peticiones
            // if ($response) {
            //     $response->headers->setCookie(
            //         Cookie::create(self::COOKIE_NAME, $carritoId, time() + self::COOKIE_EXPIRY)
            //     );
            // }
        }
        
        return $carritoId;
    }

    private function generarIdUnico(): string
    {
        // Generar ID único: timestamp + random + fingerprint del usuario
        $timestamp = microtime(true);
        $random = bin2hex(random_bytes(16));
        $fingerprint = $this->generarFingerprint();
        
        return hash('sha256', $timestamp . $random . $fingerprint);
    }

    private function generarFingerprint(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        // Crear un fingerprint básico del navegador (no 100% único pero ayuda)
        $components = [
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
            $request->headers->get('Accept-Language'),
        ];
        
        return hash('crc32', implode('|', $components));
    }
}
