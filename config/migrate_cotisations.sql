

-- Migration: Module de Gestion des Cotisations Annuelles
-- Dahira A Khiba-i Rassouloulahi

-- 1. Ajout de la colonne categorie_membre dans la table utilisateurs (si non existante)
SET @dbname = DATABASE();
SET @tablename = "utilisateurs";
SET @columnname = "categorie_membre";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE utilisateurs ADD COLUMN categorie_membre ENUM('bureau','simple','enfant') NOT NULL DEFAULT 'simple' AFTER role"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Table des Campagnes Annuelles de Cotisation
CREATE TABLE IF NOT EXISTS cotisation_campagnes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    active TINYINT(1) DEFAULT 1,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Table des Paiements de Cotisations
CREATE TABLE IF NOT EXISTS cotisation_paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campagne_id INT NOT NULL,
    user_id INT NOT NULL,
    admin_id INT DEFAULT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_paiement DATE NOT NULL,
    mode_paiement ENUM('especes','wave','orange_money','virement','autre') DEFAULT 'especes',
    recu_numero VARCHAR(50) DEFAULT NULL,
    commentaire TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campagne_id) REFERENCES cotisation_campagnes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_campagne_user (campagne_id, user_id),
    INDEX idx_date_paiement (date_paiement)
) ENGINE=InnoDB;

-- 4. Initialisation de la première campagne active (2026-2027)
INSERT INTO cotisation_campagnes (nom, date_debut, date_fin, active, description)
SELECT 'Cotisation 2026–2027', '2026-09-01', '2027-08-31', 1, 'Campagne annuelle officielle du lendemain du Gamou 2026 au Gamou 2027'
WHERE NOT EXISTS (
    SELECT 1 FROM cotisation_campagnes WHERE nom = 'Cotisation 2026–2027'
);
