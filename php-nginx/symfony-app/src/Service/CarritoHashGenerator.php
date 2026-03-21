<?php

namespace App\Service;

use App\Repository\CarritoRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CarritoHashGenerator
{
    public const COOKIE_NAME = 'carrito_hash';
    public const COOKIE_EXPIRY = 2592000; // 30 días en segundos
    private RequestStack $requestStack;
    private CarritoRepository $carritoRepository;
    
    public function __construct(
        RequestStack $requestStack,
        CarritoRepository $carritoRepository
    ) {
        $this->requestStack = $requestStack;
        $this->carritoRepository = $carritoRepository;
    }

    /**
     * Obtiene carrito_hash de la cookie o genera uno nuevo.
     *
     * @return string
     */
    public function getCarritoHash(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        // Buscar en cookie
        $carritoHash = $request->cookies->get(self::COOKIE_NAME);

        // Validar que el hash es único en BD y si no lo es, que corresponda a un carrito activo sin usuario. Si no es así, generar uno nuevo.
        if ($carritoHash) {
            $hashExiste = $this->carritoRepository->findOneByHash($carritoHash);

            if ($hashExiste) {
                $carritoExistente = $this->carritoRepository->findCarritoAnonimo($carritoHash);

                if ($carritoExistente) {
                    return $carritoHash;
                } else {
                    // El hash existe pero no es válido, generar uno nuevo
                    $carritoHash = $this->generarHashUnico();
                }
            } else {
                // El hash no existe, ok
                return $carritoHash;
            }
        } else {
            // No existe hash, generar uno nuevo
            $carritoHash = $this->generarHashUnico();
        }
        
        return $carritoHash;
    }

    private function generarHashUnico(): string
    {
        // Generar hash único: timestamp + random + fingerprint del usuario
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
