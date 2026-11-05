<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261105061242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cotation par consensus, comptes désactivables ou supprimés, réglages du site';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reglage (cle VARCHAR(100) NOT NULL, valeur LONGTEXT DEFAULT NULL, PRIMARY KEY (cle)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE croix ADD cotation VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD actif TINYINT DEFAULT 1 NOT NULL, ADD supprime_le DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE voie ADD cotation_auteur VARCHAR(10) NOT NULL');

        // Reprise des données : la cotation actuelle devient la proposition du créateur,
        // et chaque croix existante d'un autre grimpeur compte comme un vote pour elle.
        $this->addSql('UPDATE voie SET cotation_auteur = cotation');
        $this->addSql('UPDATE croix c INNER JOIN voie v ON v.id = c.voie_id SET c.cotation = v.cotation WHERE c.grimpeur_id <> v.auteur_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE reglage');
        $this->addSql('ALTER TABLE croix DROP cotation');
        $this->addSql('ALTER TABLE utilisateur DROP actif, DROP supprime_le');
        $this->addSql('ALTER TABLE voie DROP cotation_auteur');
    }
}
