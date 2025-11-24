<?php
// migrations/Version20231124000000.php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231124000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial database schema';
    }

    public function up(Schema $schema): void
    {
        // Create accounts table
        $this->addSql('
            CREATE TABLE account (
                id INT AUTO_INCREMENT NOT NULL,
                owner VARCHAR(255) NOT NULL,
                balance NUMERIC(15, 2) NOT NULL,
                currency VARCHAR(3) NOT NULL,
                version INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                INDEX idx_owner (owner),
                INDEX idx_currency (currency)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Create transfers table
        $this->addSql('
            CREATE TABLE transfer (
                id INT AUTO_INCREMENT NOT NULL,
                from_account_id INT NOT NULL,
                to_account_id INT NOT NULL,
                amount NUMERIC(15, 2) NOT NULL,
                currency VARCHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL,
                error LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                processed_at DATETIME DEFAULT NULL,
                INDEX IDX_4034A3C0F8A0BA0 (from_account_id),
                INDEX IDX_4034A3C0F8A0BA0 (to_account_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // Add foreign key constraints
        $this->addSql('
            ALTER TABLE transfer 
            ADD CONSTRAINT FK_4034A3C0F8A0BA0 FOREIGN KEY (from_account_id) 
            REFERENCES account (id)
        ');

        $this->addSql('
            ALTER TABLE transfer 
            ADD CONSTRAINT FK_4034A3C0F8A0BA1 FOREIGN KEY (to_account_id) 
            REFERENCES account (id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0F8A0BA0');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0F8A0BA1');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE transfer');
    }
}