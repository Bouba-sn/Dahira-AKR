-- Seed initial data for local development in Laragon
USE dahira_akr_v2;

-- Clean existing if any
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE commande_details;
TRUNCATE TABLE commandes;
TRUNCATE TABLE ecrits;
TRUNCATE TABLE auteurs;
TRUNCATE TABLE evenements;
TRUNCATE TABLE produits;
TRUNCATE TABLE heures_prieres;
TRUNCATE TABLE notifications;
TRUNCATE TABLE utilisateurs;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Utilisateurs (mot de passe: password)
INSERT INTO utilisateurs (id, nom, email, password, role, statut_adhesion, telephone, adresse) VALUES
(1, 'Administrateur', 'admin@dahira.sn', '$2y$12$3j0hfK8ETjlc16oPHJ9v/.lsE8Kaw9BMLNGJpWnvHCHecNMMEORYy', 'admin', 'membre', '770000000', 'Tivaouane / Dakar'),
(2, 'Mamadou Diallo', 'user@dahira.sn', '$2y$12$3j0hfK8ETjlc16oPHJ9v/.lsE8Kaw9BMLNGJpWnvHCHecNMMEORYy', 'user', 'membre', '771234567', 'Médina, Dakar');

-- 2. Heures de prières
INSERT INTO heures_prieres (id, date_debut, date_fin, fajr, dhuhr, asr, maghrib, isha, actif) VALUES
(1, CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, '05:45:00', '13:15:00', '16:45:00', '19:15:00', '20:30:00', 1);

-- 3. Événements
INSERT INTO evenements (id, type, nom_complet, adresse, date_evenement, description, image) VALUES
(1, 'dahira_samedi', 'Dahira du Samedi - Dakar', '45 Rue Blaise Diagne, Médina, Dakar', CURDATE() + INTERVAL 2 DAY + INTERVAL 16 HOUR + INTERVAL 30 MINUTE, 'Séance hebdomadaire de récital du Wird Lazim, Wazifa et Hadratul Jummah.', 'ev_1774652999.jpg'),
(2, 'gamou', 'Grand Gamou Annuel de Tivaouane', 'Grande Mosquée de Tivaouane', CURDATE() + INTERVAL 15 DAY + INTERVAL 20 HOUR, 'Célébration annuelle du Mawlid Nabawi (Naissance du Prophète PSL) sous l’égide du Khalife Général des Tidianes.', 'ev_1774653253.jpg'),
(3, 'ziar', 'Ziarra Générale chez les Moukhadams', 'Tivaouane, Quartier Darou', CURDATE() + INTERVAL 30 DAY + INTERVAL 9 HOUR, 'Visite de recueillement, de prières et de renouvellement du pacte spirituel.', 'ev_1774735568.jpeg');

-- 4. Auteurs
INSERT INTO auteurs (id, nom, biographie, photo, ordre) VALUES
(1, 'Cheikh Ahmad Tidiane Chérif', 'Fondateur vénéré de la Voie Tijaniyya (1737 - 1815), né à Aïn Madhi en Algérie et inhumé à Fès au Maroc. Maître spirituel et sceau de la sainteté muhammadienne.', 'marabout_69c954a798284.jpeg', 1),
(2, 'Cheikh Seydil Hadji Malick Sy', 'Grand propagateur de la Tijaniyya au Sénégal (1855 - 1922), affectueusement appelé Maodo. Immense savant, poète hors pair et saint homme installé à Tivaouane.', 'marabout_69c954c74ff2e.jpeg', 2),
(3, 'Serigne Babacar Sy', 'Premier Khalife de Maodo (1885 - 1957), figure emblématique de piété, d’élégance et d’organisation sociale qui a structuré les Dahiras au Sénégal.', 'marabout_69c954db1064c.jpeg', 3),
(4, 'Cheikh Ibrahim Niasse', 'Figure spirituelle mondiale de la Tijaniyya (1900 - 1975), fondateur de Médina Baye à Kaolack, ayant rassemblé des millions de disciples à travers le monde.', 'marabout_69c954ba58e8b.jpeg', 4);

-- 5. Écrits
INSERT INTO ecrits (id, auteur_id, titre, titre_arabe, contenu_arabe, contenu_francais, type) VALUES
(1, 1, 'Salatul Fatihi', 'صلاة الفاتح', 'اللَّهُمَّ صَلِّ عَلَى سَيِّدِنَا مُحَمَّدٍ الفَاتِحِ لِمَا أُغْلِقَ وَالخَاتِمِ لِمَا سَبَقَ نَاصِرِ الحَقِّ بِالحَقِّ وَالهَادِي إِلَى صِرَاطِكَ المُسْتَقِيمِ وَعَلَى آلِهِ حَقَّ قَدْرِهِ وَمِقْدَارِهِ العَظِيمِ', 'Ô Allah, répands Tes grâces sur notre seigneur Mouhammad, qui a ouvert ce qui était clos, qui a clos ce qui a précédé, le soutien de la vérité par la vérité et le guide vers Ton droit chemin, ainsi que sur sa famille selon la mesure qui lui est due et le rang suprême qui lui sied.', 'wird'),
(2, 1, 'Jawahir al-Maani', 'جواهر المعاني', 'بسم الله الرحمن الرحيم الحمد لله رب العالمين والصلاة والسلام على أشرف المرسلين', 'Perles des significations et réalisation des vœux dans le flux de Sidi Abil Abbas At-Tijani. Le traité fondamental de référence de la Voie Tijaniyya.', 'livre'),
(3, 2, 'Khilassou Zahab', 'خلاص الذهب في سيرة خير العرب', 'بِسْمِ الإِلَهِ الأَقْدَمِ الأَجَلِّ * وَخَيْرِ مَنْ خَصَّ بِكُلِّ فَضْلِ', 'L’Or Pur : Poème biographique d’une rare magnificence retraçant la vie noble et les enseignements du Prophète Muhammad (PSL).', 'qasida'),
(4, 2, 'Maa al-Aynayn', 'مع العينين', 'يا رسول الله يا خير الورى * يا شفيع الخلق في يوم الجزاء', 'Ô Messager d’Allah, Ô la meilleure des créatures, intercesseur des croyants au Jour Dernier. Hymne d’amour et d’attachement au Prophète (PSL).', 'qasida');

-- 6. Produits
INSERT INTO produits (id, nom, prix, description, details, image, stock, en_promo, prix_promo, categorie, actif) VALUES
(1, 'Chapelet Tijani en bois de santal', 8500.00, 'Chapelet traditionnel 99 grains en bois de santal naturel avec intercalaires soignés, idéal pour le Lazim et la Wazifa.', 'Bois de santal poli à la main, grain régulier 8mm, pompon soyeux.', 'prod_1775414226.jpeg', 50, 0, NULL, 'Accessoires', 1),
(2, 'Livre Jawahir al-Maani (Tome 1 & 2)', 15000.00, 'Édition de prestige bilingue arabe-français du recueil magistral de Cheikh Ali Harazim sur les enseignements de Cheikh Ahmad Tidiane.', 'Reliure dorée rigide, papier chamois 600 pages, notes explicatives complètes.', 'prod_1775414250.jpeg', 25, 1, 12500.00, 'Livres', 1),
(3, 'Parfum Musc Noir de Tivaouane (25ml)', 6500.00, 'Musc pur sans alcool aux notes profondes et boisées, parfait pour les moments de prières et les grands rassemblements.', 'Flacon roll-on 25ml, longue tenue 24h, essence naturelle certifiée.', 'prod_1775414312.jpeg', 80, 0, NULL, 'Parfums', 1),
(4, 'Djellaba Blanche Brodée Maodo', 28000.00, 'Élégante djellaba traditionnelle en coton fin de haute qualité, col officier brodé fil d’argent.', 'Tissu respirant anti-froissement, coupe droite et ample, disponible toutes tailles.', 'prod_1775414357.jpeg', 15, 1, 24000.00, 'Vêtements', 1),
(5, 'Tapis de Prière en Velours Brodé', 14000.00, 'Tapis moelleux et confortable avec motifs islamiques raffinés et dessous antidérapant.', 'Dimensions 70x110cm, velours ultra doux 800g, finition frangée or.', 'prod_1775414441.jpeg', 30, 0, NULL, 'Accessoires', 1),
(6, 'Bonnet Carré Traditionnel Serigne Babacar Sy', 7500.00, 'Le célèbre bonnet carré symbole de dignité et de raffinement, confectionné avec précision.', 'Tissu satiné épais, structure semi-rigide, finitions artisanales soignées.', 'prod_1775414474.jpeg', 40, 0, NULL, 'Vêtements', 1);

-- 7. Notifications
INSERT INTO notifications (id, user_id, titre, message, lien, lu, type) VALUES
(1, 2, 'Adhésion validée', 'Félicitations Mamadou, votre demande d’adhésion au Dahira AKR a été approuvée par l’administrateur.', '/pages/parametres.php', 0, 'adhesion'),
(2, 2, 'Nouvel événement', 'Le programme officiel du Grand Gamou Annuel de Tivaouane est disponible.', '/pages/accueil.php', 0, 'systeme');
