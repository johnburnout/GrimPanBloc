<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115083905 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Voies : choix pieds libres / pieds imposés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voie ADD pieds_libres TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voie DROP pieds_libres');
    }
}
