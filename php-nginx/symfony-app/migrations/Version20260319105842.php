<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319105842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE linea_pedido (id INT AUTO_INCREMENT NOT NULL, pedido_id INT DEFAULT NULL, producto_id INT DEFAULT NULL, nombre_producto VARCHAR(255) DEFAULT NULL, cantidad INT DEFAULT NULL, precio_unitario NUMERIC(10, 2) NOT NULL, subtotal NUMERIC(10, 2) NOT NULL, INDEX IDX_183C31654854653A (pedido_id), INDEX IDX_183C31657645698E (producto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pedido (id INT AUTO_INCREMENT NOT NULL, usuario_id INT DEFAULT NULL, estado VARCHAR(255) DEFAULT NULL, total NUMERIC(10, 2) NOT NULL, creado_en DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', pagado_en DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', referencia_pago VARCHAR(255) DEFAULT NULL, direccion_envio VARCHAR(255) DEFAULT NULL, ciudad VARCHAR(255) DEFAULT NULL, codigo_postal VARCHAR(255) DEFAULT NULL, pais VARCHAR(255) DEFAULT NULL, INDEX IDX_C4EC16CEDB38439E (usuario_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE linea_pedido ADD CONSTRAINT FK_183C31654854653A FOREIGN KEY (pedido_id) REFERENCES pedido (id)');
        $this->addSql('ALTER TABLE linea_pedido ADD CONSTRAINT FK_183C31657645698E FOREIGN KEY (producto_id) REFERENCES producto (id)');
        $this->addSql('ALTER TABLE pedido ADD CONSTRAINT FK_C4EC16CEDB38439E FOREIGN KEY (usuario_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE linea_pedido DROP FOREIGN KEY FK_183C31654854653A');
        $this->addSql('ALTER TABLE linea_pedido DROP FOREIGN KEY FK_183C31657645698E');
        $this->addSql('ALTER TABLE pedido DROP FOREIGN KEY FK_C4EC16CEDB38439E');
        $this->addSql('DROP TABLE linea_pedido');
        $this->addSql('DROP TABLE pedido');
    }
}
