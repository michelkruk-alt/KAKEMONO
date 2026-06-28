# KAKEMONO

Prototype de site multipages pour KAKEMONO Events en PHP, MySQL/SQLite, Bootstrap et jQuery.

## Démarrage local

### Mode MySQL (OVH recommandé)

Configurer les variables d’environnement avant de lancer PHP :

```bash
export DB_DRIVER=mysql
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=votre_base_ovh
export DB_USER=votre_utilisateur_ovh
export DB_PASSWORD=votre_mot_de_passe_ovh
export DB_CHARSET=utf8mb4
```

Le schéma est créé automatiquement au premier lancement.

### Mode SQLite (fallback local)

```bash
export DB_DRIVER=sqlite
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

- base MySQL (OVH) : via variables `DB_*`
- base SQLite locale (optionnelle) : `data/kakemono.sqlite`
- uploads : `uploads/`
