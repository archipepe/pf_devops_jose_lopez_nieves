<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260321100015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE carrito ADD pedido_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE carrito ADD CONSTRAINT FK_77E6BED54854653A FOREIGN KEY (pedido_id) REFERENCES pedido (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_77E6BED54854653A ON carrito (pedido_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE carrito DROP FOREIGN KEY FK_77E6BED54854653A');
        $this->addSql('DROP INDEX UNIQ_77E6BED54854653A ON carrito');
        $this->addSql('ALTER TABLE carrito DROP pedido_id');
    }
}
