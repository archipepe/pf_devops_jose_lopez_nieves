<?php

namespace App\EventSubscriber;

use App\Entity\Carrito;
use App\Service\CarritoService;
use App\Service\CarritoHashGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class CarritoSubscriber implements EventSubscriberInterface
{
    private CarritoService $carritoService;
    private CarritoHashGenerator $carritoHashGenerator;
    private LoggerInterface $logger;

    public function __construct(
        CarritoService $carritoService,
        CarritoHashGenerator $carritoHashGenerator,
        LoggerInterface $logger
    ) {
        $this->carritoService = $carritoService;
        $this->carritoHashGenerator = $carritoHashGenerator;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // La cookie sí es necesario establecerla lo más pronto posible para asegurarnos que se pueda enviar en la respuesta y el cliente la guarde:
            // - Si se establece en onKernelController ocurre lo siguiente: al acceder al checkout sin haber iniciado sesión, salta el authenticator y el onKernelController no llega a dispararse, por lo que en onKernelResponse el hash no se puede recuperar y se envía vacío y el carrito se pierde.
            // El carrito en cambio no es necesario inicializarlo tan pronto, ya que se inicializa automáticamente al acceder a él en el controlador o servicio después de que se haya resuelto el usuario autenticado (si existe)
            KernelEvents::REQUEST => ['onKernelRequest', 20],  // Mayor prioridad
            // Para poder obtener usuario en caso de existir, necesitamos ejecutar antes de que el controlador se ejecute (donde se resuelve el token de seguridad)
            // Antes lo teníamos en el kernel.request pero eso es demasiado pronto para obtener el usuario autenticado, así que lo movemos al kernel.controller con prioridad alta para que se ejecute antes de la mayoría de los listeners
            KernelEvents::CONTROLLER => ['onKernelController', 10],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        if ($this->esRequestIgnorable($request)) {
            return;
        }

        // Obtener o generar hash de carrito
        $carritoHash = $this->carritoHashGenerator->getCarritoHash();

        // Establecemos el atributo carrito_hash en la request
        $this->carritoService->setCarritoHash($request, $carritoHash);

        // Log para debugging (luego lo quitas)
        $this->logger->info('Inicializado carrito_hash para ruta: ' . $request->getPathInfo());
        $this->logger->info('Hash de carrito: ' . $carritoHash);
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        if ($this->esRequestIgnorable($request)) {
            return;
        }

        // Guardar en request attributes para acceso fácil
        $carritoHash = $this->carritoService->getCarritoHash($request);

        // Inicializar el carrito (esto crea el carrito en BD si no existe)
        $this->carritoService->setCarritoActual($request, $carritoHash);

        // Log para debugging (luego lo quitas)
        $this->logger->info('Inicializado carrito para ruta: ' . $request->getPathInfo());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->esRequestIgnorable($request)) {
            return;
        }

        $response = $event->getResponse();
        
        // Actualizamos siempre de vuelta la cookie por si hubiera cambiado el hash a un nuevo carrito o, si es de usuario, que se elimine la cookie al ser el valor null
        $carritoHash = $this->carritoService->getCarritoHash($request);

        $response->headers->setCookie(
            Cookie::create(
                CarritoHashGenerator::COOKIE_NAME, 
                $carritoHash, 
                time() + CarritoHashGenerator::COOKIE_EXPIRY
            )
        );

        $this->logger->info('Asegurar hash de carrito: ' . $carritoHash);
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

    /**
     * Determina si la request actual es de un tipo que debería ser ignorada por el subscriber (health checks, assets, admin/tools).
     *
     * @param Request $request
     * @return boolean
     */
    private function esRequestIgnorable(Request $request): bool
    {
        // IGNORAR HEALTH CHECKS COMPLETAMENTE
        if ($this->esHealthCheck($request)) {
            return true;
        }
            
        // IGNORAR RUTAS DE ASSETS ESTÁTICOS
        if ($this->esAsset($request)) {
            return true;
        }
                
        // IGNORAR RUTAS DE ADMIN/TOOLS
        if ($this->esRutaIgnorable($request)) {
            return true;
        }
        
        return false;
    }
}
