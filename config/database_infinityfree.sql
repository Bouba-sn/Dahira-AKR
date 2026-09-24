-- ==============================================================================
-- DAHIRA A KHIBA-I RASSOULOULAHI - BASE DE DONNÉES COMPLÈTE POUR INFINITYFREE
-- ==============================================================================
-- INSTRUCTIONS POUR L'IMPORTATION SUR INFINITYFREE :
-- 1. Connectez-vous à votre cPanel InfinityFree (https://dash.infinityfree.com)
-- 2. Ouvrez phpMyAdmin et sélectionnez votre base de données (ex: if0_41557146_dahira)
-- 3. Cliquez sur l'onglet "Importer", choisissez ce fichier SQL et cliquez sur "Exécuter".
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 1. Table utilisateurs
CREATE TABLE IF NOT EXISTS `utilisateurs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) DEFAULT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin','user') DEFAULT 'user',
    `categorie_membre` ENUM('bureau','simple','enfant') DEFAULT 'simple',
    `fonction` VARCHAR(100) DEFAULT 'Membre',
    `statut_adhesion` ENUM('non_membre','en_attente','membre') DEFAULT 'non_membre',
    `type_adhesion` ENUM('nouvelle','carte_existante') DEFAULT 'nouvelle',
    `carte_physique` TINYINT(1) NOT NULL DEFAULT 0,
    `numero_carte` VARCHAR(50) DEFAULT NULL,
    `telephone` VARCHAR(20) DEFAULT NULL,
    `adresse` TEXT DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `photo_membre` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table événements
CREATE TABLE IF NOT EXISTS `evenements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('gamou','ziar','dahira_samedi','autre') DEFAULT 'autre',
    `nom_complet` VARCHAR(200) NOT NULL,
    `adresse` TEXT,
    `date_evenement` DATETIME NOT NULL,
    `description` TEXT,
    `image` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table produits
CREATE TABLE IF NOT EXISTS `produits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(200) NOT NULL,
    `prix` DECIMAL(10,2) NOT NULL,
    `description` TEXT,
    `details` VARCHAR(500) DEFAULT NULL,
    `image` VARCHAR(255),
    `stock` INT DEFAULT 0,
    `en_promo` TINYINT(1) DEFAULT 0,
    `prix_promo` DECIMAL(10,2) DEFAULT NULL,
    `categorie` VARCHAR(100),
    `actif` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table commandes
CREATE TABLE IF NOT EXISTS `commandes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `nom_client` VARCHAR(150) DEFAULT NULL,
    `total` DECIMAL(10,2) NOT NULL,
    `statut` ENUM('en_attente','confirmee','expediee','livree','annulee') DEFAULT 'en_attente',
    `mode_paiement` ENUM('livraison','wave','especes') DEFAULT 'especes',
    `adresse_livraison` TEXT,
    `telephone_client` VARCHAR(20) DEFAULT NULL,
    `date_commande` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table détails commandes
CREATE TABLE IF NOT EXISTS `commande_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `commande_id` INT NOT NULL,
    `produit_id` INT NOT NULL,
    `quantite` INT NOT NULL,
    `prix_unitaire` DECIMAL(10,2) NOT NULL,
    `details` VARCHAR(500) DEFAULT NULL,
    FOREIGN KEY (`commande_id`) REFERENCES `commandes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table auteurs
CREATE TABLE IF NOT EXISTS `auteurs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(200) NOT NULL,
    `biographie` TEXT,
    `photo` VARCHAR(255),
    `ordre` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table écrits
CREATE TABLE IF NOT EXISTS `ecrits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `auteur_id` INT NOT NULL,
    `titre` VARCHAR(300) NOT NULL,
    `titre_arabe` VARCHAR(300),
    `contenu_arabe` LONGTEXT,
    `contenu_francais` LONGTEXT,
    `type` ENUM('qasida','wird','livre','discours','autre') DEFAULT 'autre',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`auteur_id`) REFERENCES `auteurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Table heures de prières
CREATE TABLE IF NOT EXISTS `heures_prieres` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `date_debut` DATE,
    `date_fin` DATE,
    `fajr` TIME NOT NULL,
    `dhuhr` TIME NOT NULL,
    `asr` TIME NOT NULL,
    `maghrib` TIME NOT NULL,
    `isha` TIME NOT NULL,
    `actif` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Table journal des appels à la prière (Adhan)
CREATE TABLE IF NOT EXISTS `adhan_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pray_name` VARCHAR(20) NOT NULL,
    `sent_at` DATETIME NOT NULL,
    INDEX `idx_pray` (`pray_name`),
    INDEX `idx_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Table notifications
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `titre` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `lien` VARCHAR(255) DEFAULT '#',
    `lu` TINYINT(1) DEFAULT 0,
    `type` ENUM('commande','adhesion','systeme') DEFAULT 'systeme',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Table push_subscriptions
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `endpoint` TEXT NOT NULL,
    `p256dh` VARCHAR(255) DEFAULT NULL,
    `auth` VARCHAR(255) DEFAULT NULL,
    `actif` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Table cotisation_campagnes
CREATE TABLE IF NOT EXISTS `cotisation_campagnes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `date_debut` DATE NOT NULL,
    `date_fin` DATE NOT NULL,
    `active` TINYINT(1) DEFAULT 1,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Table cotisation_paiements
CREATE TABLE IF NOT EXISTS `cotisation_paiements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `campagne_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `admin_id` INT DEFAULT NULL,
    `montant` DECIMAL(10,2) NOT NULL,
    `date_paiement` DATE NOT NULL,
    `mode_paiement` ENUM('especes','wave','orange_money','virement','autre') DEFAULT 'especes',
    `recu_numero` VARCHAR(50) DEFAULT NULL,
    `commentaire` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campagne_id`) REFERENCES `cotisation_campagnes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`admin_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL,
    INDEX `idx_campagne_user` (`campagne_id`, `user_id`),
    INDEX `idx_date_paiement` (`date_paiement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Table password_resets
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `telephone` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `code` VARCHAR(10) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_telephone` (`telephone`),
    INDEX `idx_email` (`email`),
    INDEX `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- DONNÉES INITIALES (SEEDS)
-- ==============================================================================

-- 1. Utilisateurs
-- Administrateur par défaut : admin@dahira.sn / mot de passe : password
-- Membre test : user@dahira.sn / mot de passe : password
INSERT IGNORE INTO `utilisateurs` (`id`, `nom`, `email`, `password`, `role`, `categorie_membre`, `statut_adhesion`, `telephone`, `adresse`) VALUES
(1, 'Administrateur', 'admin@dahira.sn', '$2y$12$3j0hfK8ETjlc16oPHJ9v/.lsE8Kaw9BMLNGJpWnvHCHecNMMEORYy', 'admin', 'bureau', 'membre', '770000000', 'Tivaouane / Dakar'),
(2, 'Mamadou Diallo', 'user@dahira.sn', '$2y$12$3j0hfK8ETjlc16oPHJ9v/.lsE8Kaw9BMLNGJpWnvHCHecNMMEORYy', 'user', 'simple', 'membre', '771234567', 'Médina, Dakar');

-- 2. Heures de prières
INSERT IGNORE INTO `heures_prieres` (`id`, `date_debut`, `date_fin`, `fajr`, `dhuhr`, `asr`, `maghrib`, `isha`, `actif`) VALUES
(1, '2026-01-01', '2027-12-31', '05:45:00', '13:15:00', '16:45:00', '19:15:00', '20:30:00', 1);

-- 3. Événements
INSERT IGNORE INTO `evenements` (`id`, `type`, `nom_complet`, `adresse`, `date_evenement`, `description`, `image`) VALUES
(1, 'dahira_samedi', 'Dahira du Samedi - Dakar', '45 Rue Blaise Diagne, Médina, Dakar', '2026-10-03 16:30:00', 'Séance hebdomadaire de récital du Wird Lazim, Wazifa et Hadratul Jummah.', 'ev_1774652999.jpg'),
(2, 'gamou', 'Grand Gamou Annuel de Tivaouane', 'Grande Mosquée de Tivaouane', '2026-10-15 20:00:00', 'Célébration annuelle du Mawlid Nabawi (Naissance du Prophète PSL) sous l’égide du Khalife Général des Tidianes.', 'ev_1774653253.jpg'),
(3, 'ziar', 'Ziarra Générale chez les Moukhadams', 'Tivaouane, Quartier Darou', '2026-11-01 09:00:00', 'Visite de recueillement, de prières et de renouvellement du pacte spirituel.', 'ev_1774735568.jpeg');

-- 4. Auteurs
INSERT IGNORE INTO `auteurs` (`id`, `nom`, `biographie`, `photo`, `ordre`) VALUES
(1, 'Cheikh Ahmad Tidiane Chérif', 'Fondateur vénéré de la Voie Tijaniyya (1737 - 1815), né à Aïn Madhi en Algérie et inhumé à Fès au Maroc. Maître spirituel et sceau de la sainteté muhammadienne.', 'marabout_69c954a798284.jpeg', 1),
(2, 'Cheikh Seydil Hadji Malick Sy', 'Grand propagateur de la Tijaniyya au Sénégal (1855 - 1922), affectueusement appelé Maodo. Immense savant, poète hors pair et saint homme installé à Tivaouane.', 'marabout_69c954c74ff2e.jpeg', 2),
(3, 'Serigne Babacar Sy', 'Premier Khalife de Maodo (1885 - 1957), figure emblématique de piété, d’élégance et d’organisation sociale qui a structuré les Dahiras au Sénégal.', 'marabout_69c954db1064c.jpeg', 3),
(4, 'Cheikh Ibrahim Niasse', 'Figure spirituelle mondiale de la Tijaniyya (1900 - 1975), fondateur de Médina Baye à Kaolack, ayant rassemblé des millions de disciples à travers le monde.', 'marabout_69c954ba58e8b.jpeg', 4);

-- 5. Écrits
INSERT IGNORE INTO `ecrits` (`id`, `auteur_id`, `titre`, `titre_arabe`, `contenu_arabe`, `contenu_francais`, `type`) VALUES
(1, 1, 'Salatul Fatihi', 'صلاة الفاتح', 'اللَّهُمَّ صَلِّ عَلَى سَيِّدِنَا مُحَمَّدٍ الفَاتِحِ لِمَا أُغْلِقَ وَالخَاتِمِ لِمَا سَبَقَ نَاصِرِ الحَقِّ بِالحَقِّ وَالهَادِي إِلَى صِرَاطِكَ المُسْتَقِيمِ وَعَلَى آلِهِ حَقَّ قَدْرِهِ وَمِقْدَارِهِ العَظِيمِ', 'Ô Allah, répands Tes grâces sur notre seigneur Mouhammad, qui a ouvert ce qui était clos, qui a clos ce qui a précédé, le soutien de la vérité par la vérité et le guide vers Ton droit chemin, ainsi que sur sa famille selon la mesure qui lui est due et le rang suprême qui lui sied.', 'wird'),
(2, 1, 'Jawahir al-Maani', 'جواهر المعاني', 'بسم الله الرحمن الرحيم الحمد لله رب العالمين والصلاة والسلام على أشرف المرسلين', 'Perles des significations et réalisation des vœux dans le flux de Sidi Abil Abbas At-Tijani. Le traité fondamental de référence de la Voie Tijaniyya.', 'livre'),
(3, 2, 'Khilassou Zahab', 'خلاص الذهب في سيرة خير العرب', 'بِسْمِ الإِلَهِ الأَقْدَمِ الأَجَلِّ * وَخَيْرِ مَنْ خَصَّ بِكُلِّ فَضْلِ', 'L’Or Pur : Poème biographique d’une rare magnificence retraçant la vie noble et les enseignements du Prophète Muhammad (PSL).', 'qasida'),
(4, 2, 'Maa al-Aynayn', 'مع العينين', 'يا رسول الله يا خير الورى * يا شفيع الخلق في يوم الجزاء', 'Ô Messager d’Allah, Ô la meilleure des créatures, intercesseur des croyants au Jour Dernier. Hymne d’amour et d’attachement au Prophète (PSL).', 'qasida');

-- 6. Produits
INSERT IGNORE INTO `produits` (`id`, `nom`, `prix`, `description`, `details`, `image`, `stock`, `en_promo`, `prix_promo`, `categorie`, `actif`) VALUES
(1, 'Chapelet Tijani en bois de santal', 8500.00, 'Chapelet traditionnel 99 grains en bois de santal naturel avec intercalaires soignés, idéal pour le Lazim et la Wazifa.', 'Bois de santal poli à la main, grain régulier 8mm, pompon soyeux.', 'prod_1775414226.jpeg', 50, 0, NULL, 'Accessoires', 1),
(2, 'Livre Jawahir al-Maani (Tome 1 & 2)', 15000.00, 'Édition de prestige bilingue arabe-français du recueil magistral de Cheikh Ali Harazim sur les enseignements de Cheikh Ahmad Tidiane.', 'Reliure dorée rigide, papier chamois 600 pages, notes explicatives complètes.', 'prod_1775414250.jpeg', 25, 1, 12500.00, 'Livres', 1),
(3, 'Parfum Musc Noir de Tivaouane (25ml)', 6500.00, 'Musc pur sans alcool aux notes profondes et boisées, parfait pour les moments de prières et les grands rassemblements.', 'Flacon roll-on 25ml, longue tenue 24h, essence naturelle certifiée.', 'prod_1775414312.jpeg', 80, 0, NULL, 'Parfums', 1),
(4, 'Djellaba Blanche Brodée Maodo', 28000.00, 'Élégante djellaba traditionnelle en coton fin de haute qualité, col officier brodé fil d’argent.', 'Tissu respirant anti-froissement, coupe droite et ample, disponible toutes tailles.', 'prod_1775414357.jpeg', 15, 1, 24000.00, 'Vêtements', 1),
(5, 'Tapis de Prière en Velours Brodé', 14000.00, 'Tapis moelleux et confortable avec motifs islamiques raffinés et dessous antidérapant.', 'Dimensions 70x110cm, velours ultra doux 800g, finition frangée or.', 'prod_1775414441.jpeg', 30, 0, NULL, 'Accessoires', 1),
(6, 'Bonnet Carré Traditionnel Serigne Babacar Sy', 7500.00, 'Le célèbre bonnet carré symbole de dignité et de raffinement, confectionné avec précision.', 'Tissu satiné épais, structure semi-rigide, finitions artisanales soignées.', 'prod_1775414474.jpeg', 40, 0, NULL, 'Vêtements', 1);

-- 7. Campagne de cotisations active
INSERT IGNORE INTO `cotisation_campagnes` (`id`, `nom`, `date_debut`, `date_fin`, `active`, `description`) VALUES
(1, 'Cotisation 2026–2027', '2026-09-01', '2027-08-31', 1, 'Campagne annuelle officielle du lendemain du Gamou 2026 au Gamou 2027');

-- 8. Notifications d'exemple
INSERT IGNORE INTO `notifications` (`id`, `user_id`, `titre`, `message`, `lien`, `lu`, `type`) VALUES
(1, 2, 'Adhésion validée', 'Félicitations Mamadou, votre adhésion au Dahira AKR a été validée.', '/pages/parametres.php', 0, 'adhesion'),
(2, 2, 'Événement à venir', 'Le programme de la Dahira du Samedi est en ligne.', '/pages/accueil.php', 0, 'systeme');

SET FOREIGN_KEY_CHECKS = 1;
