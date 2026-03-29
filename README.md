# 🕌 Dahira A Khiba-i Rassouloulahi — Application PWA

Application web progressive complète pour la Dahira, avec boutique islamique, bibliothèque et dashboard admin.

---

## ⚙️ Installation

### 1. Pré-requis
- PHP 8.0+ avec extensions PDO, PDO_MySQL
- MySQL 5.7+ ou MariaDB 10.3+
- Apache 2.4+ avec mod_rewrite activé (ou Nginx)
- Serveur web avec HTTPS (recommandé pour PWA)

### 2. Configuration de la base de données

```bash
# Créer la base de données et importer le schéma
mysql -u root -p < config/schema.sql
```

Modifier `config/database.php` avec vos identifiants :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'dahira_db');
define('DB_USER', 'votre_user');
define('DB_PASS', 'votre_password');
```

### 3. Créer le dossier uploads
```bash
mkdir -p assets/uploads
chmod 755 assets/uploads
```

### 4. Placer le projet dans votre serveur
```bash
# Exemple avec Apache sur Linux
sudo cp -r dahira/ /var/www/html/
sudo chown -R www-data:www-data /var/www/html/dahira/
```

---

## 🔐 Comptes par défaut

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Admin | admin@dahira.sn | password |
| Utilisateur | user@dahira.sn | password |

> ⚠️ **Changez impérativement ces mots de passe en production !**

Pour changer le mot de passe admin :
```php
// Générer un nouveau hash
echo password_hash('nouveau_mot_de_passe', PASSWORD_BCRYPT, ['cost' => 12]);
// Puis UPDATE utilisateurs SET password='[hash]' WHERE email='admin@dahira.sn'
```

---

## 📁 Structure des fichiers

```
dahira/
├── config/
│   ├── database.php      # Connexion PDO
│   └── schema.sql        # Structure BDD + données démo
│
├── pwa/
│   ├── manifest.json     # Manifest PWA
│   └── service-worker.js # Cache offline
│
├── includes/
│   ├── header.php        # En-tête HTML global
│   ├── footer.php        # Pied de page + JS
│   ├── navbar-bottom.php # Navigation mobile
│   └── auth.php          # Authentification & CSRF
│
├── pages/
│   ├── accueil.php       # Page d'accueil
│   ├── tidiany-way.php   # Boutique islamique
│   ├── produit.php       # Détail produit
│   ├── panier.php        # Panier (localStorage)
│   ├── commande.php      # Commande (connexion requise)
│   ├── bibliotheque.php  # Bibliothèque islamique
│   ├── auteur.php        # Page auteur
│   ├── ecrit.php         # Lecteur d'écrits
│   └── parametres.php    # Paramètres & compte
│
├── public/
│   ├── login.php         # Connexion
│   ├── register.php      # Inscription
│   └── logout.php        # Déconnexion
│
├── admin/
│   ├── dashboard.php     # Tableau de bord
│   ├── evenements.php    # CRUD événements
│   ├── boutique.php      # CRUD produits
│   ├── bibliotheque.php  # CRUD auteurs & écrits
│   ├── commandes.php     # Gestion commandes
│   ├── utilisateurs.php  # Gestion membres
│   └── rappels.php       # Rappels du jour
│
├── assets/
│   ├── uploads/          # Images uploadées
│   └── icons/            # Icônes PWA
│
├── .htaccess             # Config Apache
└── index.php             # Point d'entrée → accueil
```

---

## 🌐 Configuration PWA

### Icônes requises
Créer le dossier `assets/icons/` et y placer :
- icon-72x72.png
- icon-96x96.png
- icon-128x128.png
- icon-192x192.png
- icon-512x512.png

Vous pouvez générer ces icônes depuis un logo unique sur [realfavicongenerator.net](https://realfavicongenerator.net) ou [maskable.app](https://maskable.app).

### Service Worker
Le service worker met en cache automatiquement :
- Page d'accueil
- Bibliothèque
- Page hors ligne personnalisée

---

## 🔒 Sécurité

- ✅ PDO avec requêtes préparées (protection SQLi)
- ✅ CSRF tokens sur tous les formulaires
- ✅ Hash bcrypt (cost 12) pour les mots de passe
- ✅ Protection XSS (htmlspecialchars)
- ✅ Protection des pages admin (requireAdmin())
- ✅ Sessions sécurisées avec regenerate_id
- ✅ Headers de sécurité via .htaccess
- ✅ Blocage accès direct au dossier config/

---

## 📱 Fonctionnalités PWA

- ✅ Installable sur Android et iOS
- ✅ Mode hors ligne (pages principales)
- ✅ Notifications push (après permission)
- ✅ Icône sur l'écran d'accueil
- ✅ Plein écran sans barre d'URL

---

## 🛍️ Boutique Tidiany Way

- Liste produits avec filtres par catégorie
- Recherche texte
- Panier persistant (localStorage)
- Commande avec connexion obligatoire
- Modes de paiement : livraison, Wave, Orange Money
- Suivi commandes en temps réel

---

## 📚 Bibliothèque Islamique

- Auteurs avec biographies
- Écrits en arabe + traduction française
- Lecteur avec ajustement de la taille du texte
- Affichage bilingue côte à côte
- Partage natif (navigator.share)
- Recherche dans tous les écrits

---

## 📅 Événements

Types gérés : Gamou, Ziar, Dahira du samedi, Autres
Champs : Nom, Adresse, Date, Description, Image

---

## 🎨 Design

- Couleurs : Bleu marine (#1e3a8a) et blanc
- Police française : Outfit
- Police arabe : Amiri
- Mode sombre complet (cookie persistant)
- Interface mobile-first (max-width: 430px)
- Navigation en barre fixe en bas

---

## 🚀 Déploiement en production

1. Activer HTTPS (Let's Encrypt recommandé)
2. Décommenter la redirection HTTPS dans `.htaccess`
3. Changer les mots de passe par défaut
4. Configurer un vrai SMTP pour les notifications
5. Mettre `display_errors = Off` dans php.ini
6. Configurer `error_log` pour les erreurs PHP

---

**Baraka Allahu fikoum 🤲**
