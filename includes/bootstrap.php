<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Europe/Paris');

const ROLE_ADMIN = 0;
const ROLE_USER = 1;
const ROLE_ACCOUNTING = 2;
const ROLE_MODERATOR = 3;
const ROLE_DISABLED = 99;

const RES_CART = 'cart';
const RES_PENDING = 'pending_admin';
const RES_APPROVED = 'approved';
const RES_REJECTED = 'rejected';
const CART_HOLD_DURATION_SECONDS = 900;
const MAX_UPLOAD_SIZE_BYTES = 5_242_880;

const ROOT_PATH = __DIR__ . '/..';
const DB_PATH = ROOT_PATH . '/data/kakemono.sqlite';
const UPLOAD_DIR = ROOT_PATH . '/uploads';

$_configFile = __DIR__ . '/config.php';
if (!file_exists($_configFile)) {
    exit('Fichier de configuration manquant. Copiez includes/config.sample.php en includes/config.php et renseignez vos paramètres.');
}
require_once $_configFile;
unset($_configFile);

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

try {
    $pdo = connect_database();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    initialize_schema($pdo);
    seed_defaults($pdo);
    purge_expired_holds($pdo);
} catch (PDOException $e) {
    http_response_code(503);
    exit('Erreur de connexion à la base de données : ' . $e->getMessage() . '. Vérifiez les paramètres dans includes/config.php.');
}

function connect_database(): PDO
{
    $driver = strtolower(DB_DRIVER);

    if ($driver === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        return new PDO($dsn, DB_USER, DB_PASSWORD);
    }

    $directory = dirname(DB_PATH);
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function initialize_schema(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(120) NOT NULL,
            last_name VARCHAR(120) NOT NULL,
            address VARCHAR(255) NOT NULL,
            city VARCHAR(120) NOT NULL,
            postal_code VARCHAR(20) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_organization TINYINT(1) NOT NULL DEFAULT 0,
            role_level INT NOT NULL DEFAULT 1,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            approved_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            email VARCHAR(190) NOT NULL,
            action VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_login_history_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS association_settings (
            id TINYINT PRIMARY KEY,
            association_name VARCHAR(190) NOT NULL,
            legal_name VARCHAR(190) NOT NULL,
            address VARCHAR(255) NOT NULL,
            city VARCHAR(120) NOT NULL,
            postal_code VARCHAR(20) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            siret VARCHAR(30) NOT NULL,
            vat_number VARCHAR(120) NOT NULL,
            iban VARCHAR(50) NOT NULL,
            bic VARCHAR(20) NOT NULL,
            billing_note TEXT NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            product_type VARCHAR(30) NOT NULL,
            description TEXT NOT NULL,
            image_path VARCHAR(255) NULL,
            price_ht DECIMAL(10, 2) NOT NULL,
            price_ttc DECIMAL(10, 2) NOT NULL,
            is_quantity_limited TINYINT(1) NOT NULL DEFAULT 0,
            quantity_limit INT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(190) NOT NULL,
            year_label VARCHAR(10) NOT NULL,
            location VARCHAR(255) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            intro_text TEXT NOT NULL,
            plan_image VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS event_stands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            product_id INT NOT NULL,
            label VARCHAR(60) NOT NULL,
            x_coord DECIMAL(6, 2) NOT NULL,
            y_coord DECIMAL(6, 2) NOT NULL,
            note VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT fk_event_stands_event FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_event_stands_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS stand_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stand_id INT NOT NULL,
            option_product_id INT NOT NULL,
            CONSTRAINT fk_stand_options_stand FOREIGN KEY(stand_id) REFERENCES event_stands(id) ON DELETE CASCADE,
            CONSTRAINT fk_stand_options_product FOREIGN KEY(option_product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            stand_id INT NOT NULL,
            user_id INT NOT NULL,
            status VARCHAR(30) NOT NULL,
            hold_expires_at DATETIME NULL,
            presentation_text TEXT NOT NULL,
            ai_sourced TINYINT(1) NOT NULL DEFAULT 0,
            stand_image VARCHAR(255) NOT NULL,
            dossier_fee DECIMAL(10, 2) NOT NULL DEFAULT 0,
            terms_accepted TINYINT(1) NOT NULL DEFAULT 0,
            payment_method VARCHAR(50) NULL,
            payment_schedule TEXT NULL,
            invoice_number VARCHAR(60) NULL,
            approved_by INT NULL,
            approved_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT fk_reservations_event FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_reservations_stand FOREIGN KEY(stand_id) REFERENCES event_stands(id) ON DELETE CASCADE,
            CONSTRAINT fk_reservations_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_reservations_approved_by FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS reservation_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            product_id INT NULL,
            label VARCHAR(190) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price_ht DECIMAL(10, 2) NOT NULL,
            price_ttc DECIMAL(10, 2) NOT NULL,
            item_type VARCHAR(30) NOT NULL,
            CONSTRAINT fk_reservation_items_reservation FOREIGN KEY(reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
            CONSTRAINT fk_reservation_items_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            recipient_id INT NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_messages_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_messages_recipient FOREIGN KEY(recipient_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS mail_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(30) NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS visitor_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            stand_id INT NULL,
            visitor_name VARCHAR(120) NOT NULL,
            visitor_email VARCHAR(190) NULL,
            appreciation INT NULL,
            comment TEXT NOT NULL,
            is_private TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_visitor_feedback_event FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            CONSTRAINT fk_visitor_feedback_stand FOREIGN KEY(stand_id) REFERENCES event_stands(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_password_resets_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
        return;
    }

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            address TEXT NOT NULL,
            city TEXT NOT NULL,
            postal_code TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            is_organization INTEGER NOT NULL DEFAULT 0,
            role_level INTEGER NOT NULL DEFAULT 1,
            status TEXT NOT NULL DEFAULT 'pending',
            approved_at TEXT DEFAULT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS login_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER DEFAULT NULL,
            email TEXT NOT NULL,
            action TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            user_agent TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS association_settings (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            association_name TEXT NOT NULL,
            legal_name TEXT NOT NULL,
            address TEXT NOT NULL,
            city TEXT NOT NULL,
            postal_code TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            siret TEXT NOT NULL,
            vat_number TEXT NOT NULL,
            iban TEXT NOT NULL,
            bic TEXT NOT NULL,
            billing_note TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            product_type TEXT NOT NULL,
            description TEXT NOT NULL,
            image_path TEXT DEFAULT NULL,
            price_ht REAL NOT NULL,
            price_ttc REAL NOT NULL,
            is_quantity_limited INTEGER NOT NULL DEFAULT 0,
            quantity_limit INTEGER DEFAULT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            year_label TEXT NOT NULL,
            location TEXT NOT NULL,
            start_date TEXT NOT NULL,
            end_date TEXT NOT NULL,
            intro_text TEXT NOT NULL,
            plan_image TEXT DEFAULT NULL,
            is_active INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS event_stands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            label TEXT NOT NULL,
            x_coord REAL NOT NULL,
            y_coord REAL NOT NULL,
            note TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT
        );

        CREATE TABLE IF NOT EXISTS stand_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            stand_id INTEGER NOT NULL,
            option_product_id INTEGER NOT NULL,
            FOREIGN KEY(stand_id) REFERENCES event_stands(id) ON DELETE CASCADE,
            FOREIGN KEY(option_product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS reservations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            stand_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            hold_expires_at TEXT DEFAULT NULL,
            presentation_text TEXT NOT NULL,
            ai_sourced INTEGER NOT NULL DEFAULT 0,
            stand_image TEXT NOT NULL,
            dossier_fee REAL NOT NULL DEFAULT 0,
            terms_accepted INTEGER NOT NULL DEFAULT 0,
            payment_method TEXT DEFAULT NULL,
            payment_schedule TEXT DEFAULT NULL,
            invoice_number TEXT DEFAULT NULL,
            approved_by INTEGER DEFAULT NULL,
            approved_at TEXT DEFAULT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY(stand_id) REFERENCES event_stands(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS reservation_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reservation_id INTEGER NOT NULL,
            product_id INTEGER DEFAULT NULL,
            label TEXT NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 1,
            price_ht REAL NOT NULL,
            price_ttc REAL NOT NULL,
            item_type TEXT NOT NULL,
            FOREIGN KEY(reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
            FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER NOT NULL,
            recipient_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(recipient_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS mail_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recipient_email TEXT NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS visitor_feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id INTEGER NOT NULL,
            stand_id INTEGER DEFAULT NULL,
            visitor_name TEXT NOT NULL,
            visitor_email TEXT DEFAULT NULL,
            appreciation INTEGER DEFAULT NULL,
            comment TEXT NOT NULL,
            is_private INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY(stand_id) REFERENCES event_stands(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT NOT NULL UNIQUE,
            expires_at TEXT NOT NULL,
            used_at TEXT DEFAULT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    SQL);
}

function seed_defaults(PDO $pdo): void
{
    $now = now();

    if ((int) $pdo->query('SELECT COUNT(*) FROM association_settings')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO association_settings (id, association_name, legal_name, address, city, postal_code, email, phone, siret, vat_number, iban, bic, billing_note, updated_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'KAKEMONO Events',
            'Association KAKEMONO Events',
            '1 place des Exposants',
            'Strasbourg',
            '67000',
            'contact@kakemono.local',
            '03 88 00 00 00',
            '000 000 000 00000',
            'TVA non applicable, art. 293 B du CGI',
            'FR76 3000 4000 5000 6000 7000 800',
            'BNPAFRPPXXX',
            'Frais de dossier non remboursables. TVA à 0% pour l’activité associative.',
            $now,
        ]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM users WHERE role_level = 0')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, address, city, postal_code, email, password_hash, is_organization, role_level, status, approved_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'Admin',
            'Kakemono',
            '1 place des Exposants',
            'Strasbourg',
            '67000',
            'admin@kakemono.local',
            password_hash('Admin123!', PASSWORD_DEFAULT),
            1,
            ROLE_ADMIN,
            'active',
            $now,
            $now,
            $now,
        ]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $products = [
            ['Stand Découverte 6m²', 'space', 'Stand découverte avec signalétique de base et accès exposant.', 180, 180, 0, null],
            ['Stand Premium 9m²', 'space', 'Stand premium pour espace central avec meilleure visibilité.', 320, 320, 0, null],
            ['Prise électrique 3kW', 'option', 'Alimentation électrique 3kW pour éclairage et petit matériel.', 45, 45, 1, 20],
            ['Table 200x80cm', 'option', 'Table de présentation 200x80cm.', 18, 18, 1, 40],
            ['Banc 200x20cm', 'option', 'Banc visiteur assorti au stand.', 10, 10, 1, 60],
            ['Grille d’exposition', 'option', 'Grille d’exposition modulaire pour affichage.', 25, 25, 1, 30],
        ];
        $stmt = $pdo->prepare('INSERT INTO products (name, product_type, description, image_path, price_ht, price_ttc, is_quantity_limited, quantity_limit, active, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1, ?, ?)');
        foreach ($products as $product) {
            $stmt->execute([$product[0], $product[1], $product[2], $product[3], $product[4], $product[5], $product[6], $now, $now]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO events (title, year_label, location, start_date, end_date, intro_text, plan_image, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NULL, 1, ?, ?)');
        $stmt->execute([
            'Festival KAKEMONO Strasbourg',
            '2026',
            'Parc des expositions de Strasbourg',
            '2026-09-19',
            '2026-09-20',
            'Événement pilote avec zones de démonstration, scène activités, livre d’or et parcours Kakéminions.',
            $now,
            $now,
        ]);
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM event_stands')->fetchColumn() === 0) {
        $eventId = (int) $pdo->query('SELECT id FROM events ORDER BY id ASC LIMIT 1')->fetchColumn();
        $spaceProducts = $pdo->query("SELECT id, name FROM products WHERE product_type = 'space' ORDER BY id ASC")->fetchAll();
        $optionIds = $pdo->query("SELECT id FROM products WHERE product_type = 'option' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $stands = [
            ['A1', $spaceProducts[0]['id'], 18, 22, 'Zone découverte proche de l’accueil.'],
            ['A2', $spaceProducts[0]['id'], 36, 26, 'Stand compact pour artisanat et illustration.'],
            ['B1', $spaceProducts[1]['id'], 60, 44, 'Stand premium en allée centrale.'],
            ['B2', $spaceProducts[1]['id'], 76, 58, 'Stand premium vue scène activités.'],
        ];
        $stmt = $pdo->prepare('INSERT INTO event_stands (event_id, product_id, label, x_coord, y_coord, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $optStmt = $pdo->prepare('INSERT INTO stand_options (stand_id, option_product_id) VALUES (?, ?)');
        foreach ($stands as $stand) {
            $stmt->execute([$eventId, $stand[1], $stand[0], $stand[2], $stand[3], $stand[4], $now, $now]);
            $standId = (int) $pdo->lastInsertId();
            foreach ($optionIds as $optionId) {
                $optStmt->execute([$standId, $optionId]);
            }
        }
    }
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function json_attr(array $value): string
{
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}', ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_post_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(422);
        exit('Jeton CSRF invalide.');
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function current_user(): ?array
{
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        set_flash('warning', 'Veuillez vous connecter pour accéder à cette page.');
        redirect_to('/login.php');
    }
    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();
    if (!in_array((int) $user['role_level'], $roles, true)) {
        http_response_code(403);
        exit('Accès refusé.');
    }
    return $user;
}

function is_privileged(?array $user): bool
{
    return $user && in_array((int) $user['role_level'], [ROLE_ADMIN, ROLE_ACCOUNTING, ROLE_MODERATOR], true);
}

function role_label(int $role): string
{
    return match ($role) {
        ROLE_ADMIN => 'Niveau 0 · Administrateur',
        ROLE_USER => 'Niveau 1 · Utilisateur',
        ROLE_ACCOUNTING => 'Niveau 2 · Comptabilité',
        ROLE_MODERATOR => 'Niveau 3 · Modérateur',
        4 => 'Niveau 4 · Non utilisé',
        ROLE_DISABLED => 'Niveau 99 · Désactivé',
        default => 'Niveau inconnu',
    };
}

function get_settings(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM association_settings WHERE id = 1')->fetch() ?: [];
}

function queue_mail(PDO $pdo, string $email, string $subject, string $body): void
{
    $status = 'queued';
    $headers = "MIME-Version: 1.0\r\nContent-type:text/plain;charset=UTF-8\r\n";
    if (function_exists('mail')) {
        $sent = mail($email, $subject, $body, $headers);
        $status = $sent ? 'sent' : 'queued';
    }

    $stmt = $pdo->prepare('INSERT INTO mail_queue (recipient_email, subject, body, status, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$email, $subject, $body, $status, now()]);
}

function purge_expired_holds(PDO $pdo): void
{
    $stmt = $pdo->prepare("DELETE FROM reservations WHERE status = ? AND hold_expires_at IS NOT NULL AND hold_expires_at < ?");
    $stmt->execute([RES_CART, now()]);
}

function dossier_fee_for_date(string $date): float
{
    $targetDate = new DateTimeImmutable($date);
    $year = $targetDate->format('Y');
    $mayThreshold = new DateTimeImmutable($year . '-05-01');
    $aprilThreshold = new DateTimeImmutable($year . '-04-01');
    $januaryThreshold = new DateTimeImmutable($year . '-01-01');

    if ($targetDate >= $mayThreshold) {
        return 200.0;
    }
    if ($targetDate >= $aprilThreshold) {
        return 100.0;
    }
    if ($targetDate >= $januaryThreshold) {
        return 50.0;
    }
    return 0.0;
}

function handle_image_upload(string $field, string $targetFolder): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    if (!is_dir(UPLOAD_DIR . '/' . $targetFolder)) {
        mkdir(UPLOAD_DIR . '/' . $targetFolder, 0777, true);
    }

    if (($_FILES[$field]['size'] ?? 0) > MAX_UPLOAD_SIZE_BYTES) {
        throw new RuntimeException('Le fichier est trop volumineux (maximum 5 Mo).');
    }

    $tmpName = $_FILES[$field]['tmp_name'];
    $mime = mime_content_type($tmpName) ?: '';
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Seuls les fichiers PNG et JPG sont autorisés.');
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $relative = $targetFolder . '/' . $filename;
    $destination = UPLOAD_DIR . '/' . $relative;

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('Impossible de sauvegarder le fichier.');
    }

    return '/uploads/' . $relative;
}

function get_active_events(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM events WHERE is_active = 1 ORDER BY start_date ASC');
    return $stmt->fetchAll();
}

function get_all_events(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM events ORDER BY start_date DESC');
    return $stmt->fetchAll();
}

function get_event(PDO $pdo, int $eventId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    return $stmt->fetch() ?: null;
}

function get_stands_with_status(PDO $pdo, int $eventId): array
{
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT s.*, p.name AS product_name, p.description AS product_description, p.image_path AS product_image, p.price_ht, p.price_ttc
        FROM event_stands s
        JOIN products p ON p.id = s.product_id
        WHERE s.event_id = ?
        ORDER BY s.label ASC
    SQL);
    $stmt->execute([$eventId]);
    $stands = $stmt->fetchAll();

    $statusStmt = $pdo->prepare(<<<'SQL'
        SELECT r.*, u.first_name, u.last_name
        FROM reservations r
        JOIN users u ON u.id = r.user_id
        WHERE r.event_id = ?
          AND r.status IN (?, ?, ?)
        ORDER BY CASE r.status WHEN 'approved' THEN 1 WHEN 'pending_admin' THEN 2 ELSE 3 END, r.created_at DESC
    SQL);
    $statusStmt->execute([$eventId, RES_APPROVED, RES_PENDING, RES_CART]);
    $reservations = $statusStmt->fetchAll();
    $byStand = [];
    foreach ($reservations as $reservation) {
        if ($reservation['status'] === RES_CART && $reservation['hold_expires_at'] && $reservation['hold_expires_at'] < now()) {
            continue;
        }
        $standId = (int) $reservation['stand_id'];
        if (!isset($byStand[$standId])) {
            $byStand[$standId] = $reservation;
        }
    }

    foreach ($stands as &$stand) {
        $reservation = $byStand[(int) $stand['id']] ?? null;
        $stand['current_reservation_id'] = $reservation['id'] ?? null;
        $stand['status'] = 'available';
        $stand['occupant'] = null;
        if ($reservation) {
            if ($reservation['status'] === RES_APPROVED) {
                $stand['status'] = 'reserved';
                $stand['occupant'] = trim($reservation['first_name'] . ' ' . $reservation['last_name']);
            } else {
                $stand['status'] = 'pending';
                $stand['occupant'] = trim($reservation['first_name'] . ' ' . $reservation['last_name']);
            }
        }

        $optionStmt = $pdo->prepare(<<<'SQL'
            SELECT p.*
            FROM stand_options so
            JOIN products p ON p.id = so.option_product_id
            WHERE so.stand_id = ?
            ORDER BY p.name ASC
        SQL);
        $optionStmt->execute([(int) $stand['id']]);
        $stand['options'] = $optionStmt->fetchAll();
    }

    return $stands;
}

function get_reservation_total(PDO $pdo, int $reservationId): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(price_ttc * quantity), 0) FROM reservation_items WHERE reservation_id = ?');
    $stmt->execute([$reservationId]);
    return (float) $stmt->fetchColumn();
}

function get_reservation(PDO $pdo, int $reservationId): ?array
{
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT r.*, e.title AS event_title, e.start_date, e.end_date, e.location, s.label AS stand_label,
               u.first_name, u.last_name, u.email, u.address, u.city, u.postal_code
        FROM reservations r
        JOIN events e ON e.id = r.event_id
        JOIN event_stands s ON s.id = r.stand_id
        JOIN users u ON u.id = r.user_id
        WHERE r.id = ?
    SQL);
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch() ?: null;
    if ($reservation) {
        $itemStmt = $pdo->prepare('SELECT * FROM reservation_items WHERE reservation_id = ? ORDER BY id ASC');
        $itemStmt->execute([$reservationId]);
        $reservation['items'] = $itemStmt->fetchAll();
        $reservation['total_ttc'] = get_reservation_total($pdo, $reservationId);
    }
    return $reservation;
}

function record_login(PDO $pdo, ?int $userId, string $email, string $action): void
{
    $stmt = $pdo->prepare('INSERT INTO login_history (user_id, email, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $email, $action, client_ip(), $_SERVER['HTTP_USER_AGENT'] ?? 'CLI', now()]);
}

function ensure_user_can_reserve(array $user): void
{
    if ($user['status'] !== 'active' || (int) $user['role_level'] === ROLE_DISABLED) {
        set_flash('warning', 'Votre compte doit être validé par un administrateur avant toute réservation.');
        redirect_to('/profile.php');
    }
}

function format_price(float $amount): string
{
    return number_format($amount, 2, ',', ' ') . ' €';
}

function reservation_status_label(string $status): string
{
    return match ($status) {
        RES_CART => 'Panier / pré-réservation',
        RES_PENDING => 'En attente de validation',
        RES_APPROVED => 'Validée',
        RES_REJECTED => 'Refusée',
        default => $status,
    };
}

function generate_invoice_number(int $reservationId): string
{
    return 'KAK-' . date('Y') . '-' . str_pad((string) $reservationId, 4, '0', STR_PAD_LEFT);
}

function create_password_reset(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)')->execute([$userId, $token, $expires, now()]);
    return $token;
}

function consume_password_reset(PDO $pdo, string $token): ?int
{
    $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if ($row['expires_at'] < now()) {
        return null;
    }
    $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE id = ?')->execute([now(), (int) $row['id']]);
    return (int) $row['user_id'];
}
