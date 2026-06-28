# KAKEMONO

Prototype de site multipages pour KAKEMONO Events en PHP, MySQL/SQLite, Bootstrap et jQuery.

## Configuration

Copiez le fichier modèle et renseignez vos paramètres :

```bash
cp includes/config.sample.php includes/config.php
```

Éditez ensuite `includes/config.php` :

```php
define('DB_DRIVER', 'mysql');          // 'mysql' ou 'sqlite'
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'votre_base_ovh');
define('DB_USER',     'votre_utilisateur');
define('DB_PASSWORD', 'votre_mot_de_passe');
define('DB_CHARSET',  'utf8mb4');
```

> **Important :** `includes/config.php` est ignoré par git et ne doit jamais être versionné.

Le schéma est créé automatiquement au premier lancement.

## Démarrage local

### Mode MySQL (OVH recommandé)

Après avoir configuré `includes/config.php` (voir ci-dessus) :

```bash
php -S 127.0.0.1:8000
```

### Mode SQLite (développement local)

Dans `includes/config.php`, remplacez `'mysql'` par `'sqlite'` :

```php
define('DB_DRIVER', 'sqlite');
```

Puis :

```bash
php -S 127.0.0.1:8000
```

Puis ouvrir `http://127.0.0.1:8000/index.php`.

## Compte administrateur de démonstration

- E-mail : `admin@kakemono.local`
- Mot de passe : `Admin123!`

## Fonctions livrées

- inscription, connexion, modification de profil et validation manuelle des comptes
- rôles administrateur, utilisateur, comptabilité, modérateur, désactivé
- gestion des informations légales et comptables de l’association
- gestion des produits, options, événements, stands et plans interactifs
- réservation exposant avec panier, CGV PDF, frais de dossier et upload d’image
- validation des demandes avec mode de règlement, échéancier et facture PDF
- messagerie interne, livre d’or visiteurs et page tactile
- file d’attente mail locale (`mail_queue`) si aucun SMTP n’est disponible
- export PDF simple intégré en natif pour documents textuels courts

## Stockage

- base MySQL (OVH) : paramètres dans `includes/config.php`
- base SQLite locale (optionnelle) : `data/kakemono.sqlite`
- uploads : `uploads/`
