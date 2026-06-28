<?php

/**
 * Modèle de fichier de configuration KAKEMONO.
 *
 * Copiez ce fichier en `config.php` dans le même dossier et renseignez vos
 * paramètres réels. Le fichier `config.php` est ignoré par git et ne doit
 * jamais être versionné.
 *
 *   cp includes/config.sample.php includes/config.php
 */

// ─── Pilote de base de données ────────────────────────────────────────────────
// 'mysql'  → serveur MySQL/MariaDB (OVH, serveur dédié…)
// 'sqlite' → fichier SQLite local (développement / test)
define('DB_DRIVER', 'mysql');

// ─── Paramètres MySQL (ignorés si DB_DRIVER = 'sqlite') ──────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'nom_de_votre_base');
define('DB_USER',     'votre_utilisateur');
define('DB_PASSWORD', 'votre_mot_de_passe');
define('DB_CHARSET',  'utf8mb4');
