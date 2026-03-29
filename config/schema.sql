-- ============================================
-- DAHIRA A KHIBA-I RASSOULOULAHI - Base de données
-- ============================================

CREATE DATABASE IF NOT EXISTS dahira_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dahira_db;

-- Table utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT NULL,
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
    image VARCHAR(255),
    stock INT DEFAULT 0,
    categorie VARCHAR(100),
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table commandes
CREATE TABLE IF NOT EXISTS commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    statut ENUM('en_attente','confirmee','expediee','livree','annulee') DEFAULT 'en_attente',
    mode_paiement ENUM('livraison','wave','orange_money') DEFAULT 'livraison',
    adresse_livraison TEXT,
    date_commande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table détails commandes
CREATE TABLE IF NOT EXISTS commande_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
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

-- Table rappels du jour
CREATE TABLE IF NOT EXISTS rappels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    texte_arabe TEXT NOT NULL,
    texte_francais TEXT NOT NULL,
    source VARCHAR(200),
    date DATE,
    actif TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- ============================================
-- DONNÉES DE DÉMONSTRATION
-- ============================================

-- Admin par défaut (mot de passe: password)
INSERT INTO utilisateurs (nom, email, password, role) VALUES
('Administrateur', 'admin@dahira.sn', '$2y$12$3j0hfK8ETjlc16oPHJ9v/.lsE8Kaw9BMLNGJpWnvHCHecNMMEORYy', 'admin'),
('Mamadou Diallo', 'user@dahira.sn', '$2y$12$3j0hfK8ETjlc16oPHJ9v/.lsE8Kaw9BMLNGJpWnvHCHecNMMEORYy', 'user');

-- Événements
INSERT INTO evenements (type, nom_complet, adresse, date_evenement, description) VALUES
('gamou', 'Grand Gamou Annuel 2025', 'Tivaouane, Sénégal', '2025-03-15 08:00:00', 'Célébration annuelle du Mawlid Nabawi à Tivaouane'),
('dahira_samedi', 'Dahira du Samedi - Dakar', '45 Rue Blaise Diagne, Dakar', '2025-01-25 16:00:00', 'Réunion hebdomadaire de la Dahira avec récitation du Wird'),
('ziar', 'Ziar chez Serigne Babacar Sy', 'Tivaouane, Quartier Darou', '2025-02-08 09:00:00', 'Visite de piété et bénédiction');

-- Auteurs
INSERT INTO auteurs (nom, biographie, ordre) VALUES
('Cheikh Ahmad Tidiane Chérif', 'Fondateur de la Tijaniyya, né en 1737 à Ain Madhi en Algérie. Grand soufi et maître spirituel, il reçut la Tariqa directement du Prophète (PSL) lors d\'une vision. Ses enseignements guident des millions de croyants à travers le monde.', 1),
('Cheikh Seydil Hadji Malick Sy', 'Grand khalife et pilier de la Tijaniyya au Sénégal, né en 1855. Érudit exceptionnel, poète mystique et réformateur social. Ses qasidas en arabe sont chantées lors de tous les rassemblements tijanis.', 2),
('Serigne Babacar Sy', 'Fils et successeur de Cheikh Malick Sy, il consolida la Tijaniyya au Sénégal et étendit son rayonnement. Connu pour sa sagesse et sa générosité, il fut un guide spirituel incontesté.', 3);

-- Écrits
INSERT INTO ecrits (auteur_id, titre, titre_arabe, contenu_arabe, contenu_francais, type) VALUES
(1, 'Jawahir al-Ma\'ani', 'جواهر المعاني', 'بسم الله الرحمن الرحيم\nالحمد لله رب العالمين', 'Au nom d\'Allah, le Très Miséricordieux, le Tout Miséricordieux.\nLoange à Allah, Seigneur des mondes.', 'livre'),
(2, 'Maa al-Aynayn', 'ماء العينين', 'يا نبي الله يا خير الورى\nأنت نور الله في كل الدجى', 'Ô Prophète d\'Allah, ô meilleur des créatures\nTu es la lumière d\'Allah dans toutes les ténèbres', 'qasida'),
(2, 'Rawdatun Nayireen', 'روضة النيرين', 'صلى الإله على النبي محمد\nخير الأنام وأكرم المبعوث', 'Qu\'Allah bénisse le Prophète Muhammad\nLe meilleur des hommes et le plus noble envoyé', 'qasida');

-- Rappels
INSERT INTO rappels (texte_arabe, texte_francais, source) VALUES
('اللَّهُمَّ صَلِّ عَلَى سَيِّدِنَا مُحَمَّدٍ الفَاتِحِ لِمَا أُغْلِقَ', 'Ô Allah, bénis notre Seigneur Muhammad, celui qui ouvre ce qui était fermé', 'Wird Tijaniyya - Salatul Fatihi'),
('أَعُوذُ بِاللَّهِ مِنَ الشَّيْطَانِ الرَّجِيمِ\nبِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيمِ', 'Je cherche refuge auprès d\'Allah contre le diable maudit.\nAu nom d\'Allah, le Tout Miséricordieux, le Très Miséricordieux.', 'Coran'),
('سُبْحَانَ اللَّهِ وَبِحَمْدِهِ سُبْحَانَ اللَّهِ الْعَظِيمِ', 'Gloire à Allah et louange à Lui, Gloire à Allah l\'Immense', 'Hadith Sahih');

-- Produits
INSERT INTO produits (nom, prix, description, stock, categorie) VALUES
('Chapelet en bois de santal 99 grains', 8500, 'Chapelet traditionnel en bois de santal parfumé, 99 grains avec séparateurs en métal doré', 50, 'Accessoires'),
('Livre: Jawahir al-Maani (Français)', 12000, 'Traduction française complète du Jawahir al-Maani de Cheikh Ahmad Tidiane Chérif', 30, 'Livres'),
('Parfum Musc Tijaniyya 25ml', 6500, 'Parfum musc de haute qualité, idéal pour les cérémonies religieuses', 100, 'Parfums'),
('Djellaba Homme Blanche', 25000, 'Djellaba traditionnelle en coton fin, taille universelle ajustable', 20, 'Vêtements'),
('Tapis de prière brodé', 15000, 'Tapis de prière en velours avec motifs géométriques islamiques brodés', 40, 'Accessoires');
