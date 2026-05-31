<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * EJEMPLO CONTRACT AFTER EXPAND PHASE: Eliminar columna email después de asegurar la implantación correcta de email_address (incompatible con v1).
 * 
 * Patrón: Expand/Contract para Blue-Green Safety
 * - v1 (BLUE): Usa email, ignora columna email_address.
 * - v2 (GREEN): Usa email_address, pero mantiene email para compatibilidad.
 * - v2 (GREEN): Usa email_address, y elimina email para limpiar el esquema después de la transición probada con éxito.
 */
final class Version20260401100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CONTRACT: Eliminar columna email para limpiar el esquema después de la transición probada con éxito a email_address.';
    }

    public function up(Schema $schema): void
    {
        // Paso 1: Eliminar el índice UNIQUE en la columna email
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');

        // Paso 2: Eliminar la columna email
        $this->addSql('ALTER TABLE user DROP COLUMN email');
    }

    public function down(Schema $schema): void
    {
        // Sin implementar
    }
}
