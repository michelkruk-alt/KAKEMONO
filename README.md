# KAKEMONO

Prototype de site multipages pour KAKEMONO Events en PHP, SQLite, Bootstrap et jQuery.

## Démarrage local

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

## Stockage

- base SQLite : `data/kakemono.sqlite`
- uploads : `uploads/`
