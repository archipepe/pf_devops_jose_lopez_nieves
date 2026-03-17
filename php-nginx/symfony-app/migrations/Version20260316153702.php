<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316153702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE carrito (id INT AUTO_INCREMENT NOT NULL, usuario_id INT DEFAULT NULL, estado_id INT NOT NULL, session_id VARCHAR(255) DEFAULT NULL, creado_en DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', actualizado_en DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', finalizado_en DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_77E6BED5DB38439E (usuario_id), INDEX IDX_77E6BED59F5A440B (estado_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE estado_carrito (id INT AUTO_INCREMENT NOT NULL, control VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE producto_carrito (id INT AUTO_INCREMENT NOT NULL, carrito_id INT DEFAULT NULL, producto_id INT DEFAULT NULL, cantidad INT DEFAULT NULL, INDEX IDX_E62FF5EDDE2CF6E7 (carrito_id), INDEX IDX_E62FF5ED7645698E (producto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE carrito ADD CONSTRAINT FK_77E6BED5DB38439E FOREIGN KEY (usuario_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE carrito ADD CONSTRAINT FK_77E6BED59F5A440B FOREIGN KEY (estado_id) REFERENCES estado_carrito (id)');
        $this->addSql('ALTER TABLE producto_carrito ADD CONSTRAINT FK_E62FF5EDDE2CF6E7 FOREIGN KEY (carrito_id) REFERENCES carrito (id)');
        $this->addSql('ALTER TABLE producto_carrito ADD CONSTRAINT FK_E62FF5ED7645698E FOREIGN KEY (producto_id) REFERENCES producto (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE carrito DROP FOREIGN KEY FK_77E6BED5DB38439E');
        $this->addSql('ALTER TABLE carrito DROP FOREIGN KEY FK_77E6BED59F5A440B');
        $this->addSql('ALTER TABLE producto_carrito DROP FOREIGN KEY FK_E62FF5EDDE2CF6E7');
        $this->addSql('ALTER TABLE producto_carrito DROP FOREIGN KEY FK_E62FF5ED7645698E');
        $this->addSql('DROP TABLE carrito');
        $this->addSql('DROP TABLE estado_carrito');
        $this->addSql('DROP TABLE producto_carrito');
    }
}
