-- ============================================
-- DAHIRA A KHIBA-I RASSOULOULAHI - Base de données
-- Schéma complet consolidé prêt pour hébergement
-- ============================================

-- Table utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) DEFAULT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    categorie_membre ENUM('bureau','simple','enfant') DEFAULT 'simple',
    fonction VARCHAR(100) DEFAULT 'Membre',
    statut_adhesion ENUM('non_membre','en_attente','membre') DEFAULT 'non_membre',
    type_adhesion ENUM('nouvelle','carte_existante') DEFAULT 'nouvelle',
    carte_physique TINYINT(1) NOT NULL DEFAULT 0,
    numero_carte VARCHAR(50) DEFAULT NULL,
    telephone VARCHAR(20) DEFAULT NULL,
    adresse TEXT DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    photo_membre VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table événements
CREATE TABLE IF NOT EXISTS evenements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('gamou','ziar','dahira_samedi','autre') DEFAULT 'autre',
    nom_complet VARCHAR(200) NOT NULL,
    adresse TEXT,
    date_evenement DATETIME NOT NULL,
    description TEXT,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table produits
CREATE TABLE IF NOT EXISTS produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(200) NOT NULL,
    prix DECIMAL(10,2) NOT NULL,
    description TEXT,
    details VARCHAR(500) DEFAULT NULL,
    image VARCHAR(255),
    stock INT DEFAULT 0,
    en_promo TINYINT(1) DEFAULT 0,
    prix_promo DECIMAL(10,2) DEFAULT NULL,
    categorie VARCHAR(100),
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table commandes (supporte clients avec application et ventes directes au guichet)
CREATE TABLE IF NOT EXISTS commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    nom_client VARCHAR(150) DEFAULT NULL,
    total DECIMAL(10,2) NOT NULL,
    statut ENUM('en_attente','confirmee','expediee','livree','annulee') DEFAULT 'en_attente',
    mode_paiement ENUM('livraison','wave','especes') DEFAULT 'especes',
    adresse_livraison TEXT,
    telephone_client VARCHAR(20) DEFAULT NULL,
    date_commande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table détails commandes
CREATE TABLE IF NOT EXISTS commande_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    details VARCHAR(500) DEFAULT NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id)
) ENGINE=InnoDB;

-- Table auteurs
CREATE TABLE IF NOT EXISTS auteurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(200) NOT NULL,
    biographie TEXT,
    photo VARCHAR(255),
    ordre INT DEFAULT 0
) ENGINE=InnoDB;

-- Table écrits
CREATE TABLE IF NOT EXISTS ecrits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auteur_id INT NOT NULL,
    titre VARCHAR(300) NOT NULL,
    titre_arabe VARCHAR(300),
    contenu_arabe LONGTEXT,
    contenu_francais LONGTEXT,
    type ENUM('qasida','wird','livre','discours','autre') DEFAULT 'autre',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auteur_id) REFERENCES auteurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table heures de prières
CREATE TABLE IF NOT EXISTS heures_prieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_debut DATE,
    date_fin DATE,
    fajr TIME NOT NULL,
    dhuhr TIME NOT NULL,
    asr TIME NOT NULL,
    maghrib TIME NOT NULL,
    isha TIME NOT NULL,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table journal des appels à la prière (Adhan)
CREATE TABLE IF NOT EXISTS adhan_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pray_name VARCHAR(20) NOT NULL,
    sent_at DATETIME NOT NULL,
    INDEX idx_pray (pray_name),
    INDEX idx_sent (sent_at)
) ENGINE=InnoDB;

-- Table notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    lien VARCHAR(255) DEFAULT '#',
    lu TINYINT(1) DEFAULT 0,
    type ENUM('commande','adhesion','systeme') DEFAULT 'systeme',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table push_subscriptions
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) DEFAULT NULL,
    auth VARCHAR(255) DEFAULT NULL,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table cotisation_campagnes
CREATE TABLE IF NOT EXISTS cotisation_campagnes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    active TINYINT(1) DEFAULT 1,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table cotisation_paiements
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

-- Table password_resets (codes de vérification pour mot de passe oublié)
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_code (code)
) ENGINE=InnoDB;

