<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261104220519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE photo (id INT AUTO_INCREMENT NOT NULL, fichier VARCHAR(255) NOT NULL, largeur INT NOT NULL, hauteur INT NOT NULL, cree_le DATETIME NOT NULL, secteur_id INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_14B784189F7E4405 (secteur_id), INDEX IDX_14B7841860BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prise (id INT AUTO_INCREMENT NOT NULL, x DOUBLE PRECISION NOT NULL, y DOUBLE PRECISION NOT NULL, rayon DOUBLE PRECISION NOT NULL, type VARCHAR(10) NOT NULL, voie_id INT NOT NULL, INDEX IDX_800A3088EAAC89CF (voie_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE secteur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, pseudo VARCHAR(50) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, cree_le DATETIME NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), UNIQUE INDEX UNIQ_1D1C63B386CC499D (pseudo), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE voie (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, systeme VARCHAR(10) NOT NULL, cotation VARCHAR(10) NOT NULL, rang INT NOT NULL, active TINYINT NOT NULL, cree_le DATETIME NOT NULL, photo_id INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_A57CE97895796DE381CFA4CE (systeme, rang), INDEX IDX_A57CE9787E9E4C8C (photo_id), INDEX IDX_A57CE97860BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784189F7E4405 FOREIGN KEY (secteur_id) REFERENCES secteur (id)');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B7841860BB6FE6 FOREIGN KEY (auteur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE prise ADD CONSTRAINT FK_800A3088EAAC89CF FOREIGN KEY (voie_id) REFERENCES voie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voie ADD CONSTRAINT FK_A57CE9787E9E4C8C FOREIGN KEY (photo_id) REFERENCES photo (id)');
        $this->addSql('ALTER TABLE voie ADD CONSTRAINT FK_A57CE97860BB6FE6 FOREIGN KEY (auteur_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B784189F7E4405');
        $this->addSql('ALTER TABLE photo DROP FOREIGN KEY FK_14B7841860BB6FE6');
        $this->addSql('ALTER TABLE prise DROP FOREIGN KEY FK_800A3088EAAC89CF');
        $this->addSql('ALTER TABLE voie DROP FOREIGN KEY FK_A57CE9787E9E4C8C');
        $this->addSql('ALTER TABLE voie DROP FOREIGN KEY FK_A57CE97860BB6FE6');
        $this->addSql('DROP TABLE photo');
        $this->addSql('DROP TABLE prise');
        $this->addSql('DROP TABLE secteur');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE voie');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
