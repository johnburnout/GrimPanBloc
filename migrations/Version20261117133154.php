<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117133154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recalage d\'une photo à une autre (changement de photo d\'une voie) et propositions ignorées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE recalage (id INT AUTO_INCREMENT NOT NULL, paires JSON NOT NULL, matrice JSON NOT NULL, cree_le DATETIME NOT NULL, maj_le DATETIME NOT NULL, photo_source_id INT NOT NULL, photo_cible_id INT NOT NULL, UNIQUE INDEX UNIQ_576A88D498C24A403A524183 (photo_source_id, photo_cible_id), INDEX IDX_576A88D498C24A40 (photo_source_id), INDEX IDX_576A88D43A524183 (photo_cible_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recalage_voie_ignoree (recalage_id INT NOT NULL, voie_id INT NOT NULL, INDEX IDX_444B74D2B2FCC28E (recalage_id), INDEX IDX_444B74D2EAAC89CF (voie_id), PRIMARY KEY (recalage_id, voie_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE recalage ADD CONSTRAINT FK_576A88D498C24A40 FOREIGN KEY (photo_source_id) REFERENCES photo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recalage ADD CONSTRAINT FK_576A88D43A524183 FOREIGN KEY (photo_cible_id) REFERENCES photo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recalage_voie_ignoree ADD CONSTRAINT FK_444B74D2B2FCC28E FOREIGN KEY (recalage_id) REFERENCES recalage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recalage_voie_ignoree ADD CONSTRAINT FK_444B74D2EAAC89CF FOREIGN KEY (voie_id) REFERENCES voie (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recalage DROP FOREIGN KEY FK_576A88D498C24A40');
        $this->addSql('ALTER TABLE recalage DROP FOREIGN KEY FK_576A88D43A524183');
        $this->addSql('ALTER TABLE recalage_voie_ignoree DROP FOREIGN KEY FK_444B74D2B2FCC28E');
        $this->addSql('ALTER TABLE recalage_voie_ignoree DROP FOREIGN KEY FK_444B74D2EAAC89CF');
        $this->addSql('DROP TABLE recalage');
        $this->addSql('DROP TABLE recalage_voie_ignoree');
    }
}
