<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * EJEMPLO EXPAND PHASE: Agregar email_address sin eliminar email (compatible con v1)
 * 
 * Patrón: Expand/Contract para Blue-Green Safety
 * - v1 (BLUE): Usa email, ignora columna email_address.
 * - v2 (GREEN): Usa email_address, pero mantiene email para compatibilidad.
 * 
 * Ambas versiones coexisten sin conflicto.
 */
final class Version20260401100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'EXPAND: Añadir columna email_address (email no se cambia para asegurar retrocompatibilidad con v1).';
    }

    public function up(Schema $schema): void
    {
        // Paso 1: Crear la nueva columna (permite NULL temporalmente)
        $this->addSql('ALTER TABLE user ADD COLUMN email_address VARCHAR(180) DEFAULT NULL');

        // Paso 2: Copiar los valores existentes de email a email_address
        $this->addSql('UPDATE user SET email_address = email');

        // Paso 3: Crear índice UNIQUE en la nueva columna
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL_ADDRESS ON user(email_address)');

        // Paso 4: Mantener email para v1
        // No ejecutamos: DROP COLUMN email
    }

    public function down(Schema $schema): void
    {
        // Revertir el índice
        $this->addSql('DROP INDEX UNIQ_USER_EMAIL_ADDRESS ON user');

        // Eliminar la columna
        $this->addSql('ALTER TABLE user DROP COLUMN email_address');
    }
}
