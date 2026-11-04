<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261104221647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE croix (id INT AUTO_INCREMENT NOT NULL, cree_le DATETIME NOT NULL, voie_id INT NOT NULL, grimpeur_id INT NOT NULL, UNIQUE INDEX croix_unique (voie_id, grimpeur_id), INDEX IDX_70EC376AEAAC89CF (voie_id), INDEX IDX_70EC376A86AFB02F (grimpeur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE croix ADD CONSTRAINT FK_70EC376AEAAC89CF FOREIGN KEY (voie_id) REFERENCES voie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE croix ADD CONSTRAINT FK_70EC376A86AFB02F FOREIGN KEY (grimpeur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE croix DROP FOREIGN KEY FK_70EC376AEAAC89CF');
        $this->addSql('ALTER TABLE croix DROP FOREIGN KEY FK_70EC376A86AFB02F');
        $this->addSql('DROP TABLE croix');
    }
}
