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