<?php

namespace App\EventSubscriber;

use App\Service\CarritoService;
use App\Service\CarritoIdGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class CarritoSubscriber implements EventSubscriberInterface
{
    private CarritoService $carritoService;
    private CarritoIdGenerator $carritoIdGenerator;
    private LoggerInterface $logger;

    public function __construct(
        CarritoService $carritoService,
        CarritoIdGenerator $carritoIdGenerator,
        LoggerInterface $logger
    ) {
        $this->carritoService = $carritoService;
        $this->carritoIdGenerator = $carritoIdGenerator;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20], // Prioridad alta
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // IGNORAR HEALTH CHECKS COMPLETAMENTE
        if ($this->esHealthCheck($request)) {
            return;
        }
            
        // IGNORAR RUTAS DE ASSETS ESTÁTICOS
        if ($this->esAsset($request)) {
            return;
        }
                
        // IGNORAR RUTAS DE ADMIN/TOOLS
        if ($this->esRutaIgnorable($request)) {
            return;
        }

        // Log para debugging (luego lo quitas)
        $this->logger->info('Inicializando carrito para ruta: ' . $request->getPathInfo());
        
        // Obtener o generar ID de carrito
        $carritoId = $this->carritoIdGenerator->getCarritoId();

        // Guardar en request attributes para acceso fácil
        $request->attributes->set('carrito_id', $carritoId);
        
        // Inicializar el carrito (esto crea el carrito en BD si no existe)
        $carrito = $this->carritoService->getCarritoActual($carritoId);
        
        // Guardar carrito en request attributes
        $request->attributes->set('carrito', $carrito);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // IGNORAR HEALTH CHECKS COMPLETAMENTE
        if ($this->esHealthCheck($request)) {
            return;
        }
            
        // IGNORAR RUTAS DE ASSETS ESTÁTICOS
        if ($this->esAsset($request)) {
            return;
        }
                
        // IGNORAR RUTAS DE ADMIN/TOOLS
        if ($this->esRutaIgnorable($request)) {
            return;
        }

        $response = $event->getResponse();
        
        // Asegurar que la cookie existe (por si acaso)
        // TODO: Prevalece la fecha de caducidad de primera creación. Si el usuario entra más veces, no se actualiza
        $carritoId = $request->attributes->get('carrito_id');
        if ($carritoId && !$request->cookies->has(CarritoIdGenerator::COOKIE_NAME)) {
            $response->headers->setCookie(
                Cookie::create(
                    CarritoIdGenerator::COOKIE_NAME, 
                    $carritoId, 
                    time() + CarritoIdGenerator::COOKIE_EXPIRY
                )
            );
        }
    }

    private function esHealthCheck(Request $request): bool
    {
        // Método 1: Detectar por ruta específica
        if (str_contains($request->getPathInfo(), '/health') || 
            str_contains($request->getPathInfo(), '/healthcheck')) {
            return true;
        }
        
        // Método 2: Detectar por User-Agent (muchos health checks tienen UA específico)
        $userAgent = $request->headers->get('User-Agent', '');
        if (str_contains($userAgent, 'curl') || 
            str_contains($userAgent, 'wget') ||
            str_contains($userAgent, 'healthcheck') ||
            $userAgent === '') {  // Algunos health checks no envían UA
            return true;
        }
        
        // Método 3: Detectar por IP (si es el balanceador/health checker)
        # TODO: La última IP es desde donde pruebas, la quitas para poder depurar
        # $trustedProxies = ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];
        $trustedProxies = ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12'];
        $clientIp = $request->getClientIp();
        foreach ($trustedProxies as $proxy) {
            if ($this->ipInRange($clientIp, $proxy)) {
                return true;
            }
        }
        
        return false;
    }

    private function esAsset(Request $request): bool
    {
        $assets = ['/css/', '/js/', '/images/', '/fonts/', '/favicon'];
        foreach ($assets as $asset) {
            if (str_starts_with($request->getPathInfo(), $asset)) {
                return true;
            }
        }
        return false;
    }

    private function esRutaIgnorable(Request $request): bool
    {
        $ignoredPaths = [
            '/_profiler',
            '/_wdt',
            '/_error',
            '/admin/tool',
            '/api/doc',
            '/health',
            '/metrics'
        ];
        
        foreach ($ignoredPaths as $path) {
            if (str_starts_with($request->getPathInfo(), $path)) {
                return true;
            }
        }
        return false;
    }

    private function ipInRange($ip, $range): bool
    {
        if (strpos($range, '/')) {
            list($range, $netmask) = explode('/', $range, 2);
            $range_decimal = ip2long($range);
            $ip_decimal = ip2long($ip);
            $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
            $netmask_decimal = ~ $wildcard_decimal;
            return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
        } else {
            return $ip === $range;
        }
    }
}
