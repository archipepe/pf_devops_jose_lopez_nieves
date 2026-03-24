### Obtener servicios Docker

```bash
docker ps
```

### Acceder al servicio con bash

```bash
docker exec -it docker-compose-symfony-php-nginx-service-1 bash
```

### Crear entidad Carrito

```bash
php bin/console make:entity

# Class name of the entity to create or update (e.g. AgreeableGnome): Carrito

# New property name (press <return> to stop adding fields):
# > usuario

# Field type (enter ? to see all types) [string]:
# > relation

# What class should this entity be related to?:
# > User

# # Relation type? [ManyToOne, OneToMany, ManyToMany, OneToOne]:
# > ManyToOne

# Is the Carrito.usuario property allowed to be null (nullable)? (yes/no) [yes]:
# >

# Do you want to add a new property to User so that you can access/update Carrito objects from it - e.g. $user->getCarritos()? (yes/no) [yes]:
# >

# A new property will also be added to the User class so that you can access the related Carrito objects from it.

# New field name inside User [carritos]:
# >

```

### Crear entidades ProductoCarrito y EstadoCarrito

En caso de error, leerlo y comprobar la compatibilidad de versiones.
Para "symfony/maker-bundle": "^1.0" hay que usar "doctrine/orm": "^2.15"
Borrar lo que se haya creado, normalmente Carrito y CarritoRepository, borrar caché y volver a empezar.

### Crear fixtures con los estados iniciales

```bash
composer require --dev "doctrine/doctrine-fixtures-bundle:^4.0"
```

Implementar la creación de los estados en AppFixtures->load()

### Crear la migración

```bash
php bin/console make:migration
```

### Ejecutar la migración

```bash
php bin/console doctrine:migrations:migrate
```

### Cargar los datos de prueba

```bash
php bin/console doctrine:fixtures:load
```

### Nuevo controlador
```bash
php bin/console make:controller
```

### Cambiar propiedad sessionId por hash en Carrito
Editar la clase Carrito.
Crear y ejecutar la migración.

### OpenTelemetry

### Instalar OpenTelemetry extension
Se incluyen los pasos a modo de referencia. La instalación se hace en Dockerfile.app
```bash
apt-get install gcc make autoconf php-dev
pecl install opentelemetry
nano /etc/php/8.3/fpm/php.ini

# Añadir al final y guardar
[opentelemetry]
extension=opentelemetry.so

# Comprobar
php-fpm8.3 -m | grep opentelemetry
```


### Instalar OpenTelemetry Symfony
```bash
composer require ext-opentelemetry
composer require open-telemetry/sdk
composer require open-telemetry/exporter-otlp
composer require open-telemetry/opentelemetry-auto-symfony
```

Para usar la instrumentación automática de trazas, declarar las siguientes variables en el servicio de la app en docker-compose.yml:
```yml
environment:
    OTEL_PHP_AUTOLOAD_ENABLED: true
    OTEL_SERVICE_NAME: mi-tienda-symfony
    OTEL_TRACES_EXPORTER: otlp
    OTEL_EXPORTER_OTLP_PROTOCOL: http/protobuf
    OTEL_EXPORTER_OTLP_ENDPOINT: http://otel-collector:4318
    OTEL_PROPAGATORS: baggage,tracecontext
```

Para aprovechar el tracer global en la instrumentación manual de trazas, obtenerlo mediante:
```php
$this->tracer = Globals::tracerProvider()->getTracer(
    'symfony-app',
    '1.0.0'
);
```

Instrumentación de logs

```bash
composer require open-telemetry/opentelemetry-logger-monolog
```

Crear src/OpenTelemetry/Logging/OtelHandler.php que recupere el logger global:
```php
public function __construct()
{
    parent::__construct(
        Globals::loggerProvider(),
        \Psr\Log\LogLevel::INFO
    );
}
```

Añadir el nuevo handler a monolog.yaml:
```yaml
monolog:
    handlers:
        main:
            ...
        console:
            ...
        otlp:
            type: service
            id: App\OpenTelemetry\Logging\OtelHandler
            level: warning
```

Instrumentación de métricas

Crear src/OpenTelemetry/Metrics/MyMetrics.php que recupere metrics globales:

```php
$meter = Globals::meterProvider()->getMeter(
    'symfony-app', '1.0.0'
);
```