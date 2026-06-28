<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();
ensure_user_can_reserve($user);
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/events.php');
}

require_post_csrf();

if ($action === 'add') {
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $standId = (int) ($_POST['stand_id'] ?? 0);
    $event = get_event($pdo, $eventId);
    if (!$event) {
        set_flash('danger', 'Événement introuvable.');
        redirect_to('/events.php');
    }

    $standStmt = $pdo->prepare('SELECT s.*, p.name, p.price_ht, p.price_ttc FROM event_stands s JOIN products p ON p.id = s.product_id WHERE s.id = ? AND s.event_id = ?');
    $standStmt->execute([$standId, $eventId]);
    $stand = $standStmt->fetch();
    if (!$stand) {
        set_flash('danger', 'Stand introuvable.');
        redirect_to('/event.php?id=' . $eventId);
    }

    $conflictStmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE stand_id = ? AND status IN (?, ?, ?) AND (hold_expires_at IS NULL OR hold_expires_at >= ?)');
    $conflictStmt->execute([$standId, RES_CART, RES_PENDING, RES_APPROVED, now()]);
    if ((int) $conflictStmt->fetchColumn() > 0) {
        set_flash('warning', 'Ce stand n’est plus disponible.');
        redirect_to('/event.php?id=' . $eventId);
    }

    try {
        $imagePath = handle_image_upload('stand_image', 'stands');
        if (!$imagePath) {
            throw new RuntimeException('Le visuel du stand est obligatoire.');
        }

        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO reservations (event_id, stand_id, user_id, status, hold_expires_at, presentation_text, ai_sourced, stand_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            $eventId,
            $standId,
            (int) $user['id'],
            RES_CART,
            date('Y-m-d H:i:s', time() + CART_HOLD_DURATION_SECONDS),
            trim($_POST['presentation_text'] ?? ''),
            isset($_POST['ai_sourced']) ? 1 : 0,
            $imagePath,
            now(),
            now(),
        ]);
        $reservationId = (int) $pdo->lastInsertId();

        $itemInsert = $pdo->prepare('INSERT INTO reservation_items (reservation_id, product_id, label, quantity, price_ht, price_ttc, item_type) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $itemInsert->execute([$reservationId, (int) $stand['product_id'], $stand['label'] . ' · ' . $stand['name'], 1, (float) $stand['price_ht'], (float) $stand['price_ttc'], 'base']);

        $optionIds = array_map('intval', $_POST['option_ids'] ?? []);
        if ($optionIds) {
            $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
            $optStmt = $pdo->prepare("SELECT p.* FROM products p JOIN stand_options so ON so.option_product_id = p.id WHERE so.stand_id = ? AND p.id IN ($placeholders) AND p.product_type = 'option'");
            $optStmt->execute(array_merge([$standId], $optionIds));
            foreach ($optStmt->fetchAll() as $option) {
                $itemInsert->execute([$reservationId, (int) $option['id'], $option['name'], 1, (float) $option['price_ht'], (float) $option['price_ttc'], 'option']);
            }
        }

        $pdo->commit();
        set_flash('success', 'Stand ajouté au panier et bloqué temporairement pendant ' . (CART_HOLD_DURATION_SECONDS / 60) . ' minutes.');
        redirect_to('/cart.php');
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', $throwable->getMessage());
        redirect_to('/event.php?id=' . $eventId);
    }
}

if ($action === 'update') {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $reservation = get_reservation($pdo, $reservationId);
    if (!$reservation || (int) $reservation['user_id'] !== (int) $user['id'] || $reservation['status'] !== RES_CART) {
        set_flash('danger', 'Article de panier invalide.');
        redirect_to('/cart.php');
    }

    $presentationText = trim($_POST['presentation_text'] ?? '');
    if ($presentationText === '') {
        set_flash('warning', 'La présentation du travail exposé est obligatoire.');
        redirect_to('/cart.php');
    }

    try {
        $imagePath = handle_image_upload('stand_image', 'stands') ?: $reservation['stand_image'];
        $rawOptionIds = array_map('intval', $_POST['option_ids'] ?? []);
        $positiveOptionIds = array_filter($rawOptionIds, static fn (int $id): bool => $id > 0);
        $optionIds = array_values(array_unique($positiveOptionIds));
        $itemInsert = $pdo->prepare('INSERT INTO reservation_items (reservation_id, product_id, label, quantity, price_ht, price_ttc, item_type) VALUES (?, ?, ?, ?, ?, ?, ?)');

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE reservations SET presentation_text = ?, ai_sourced = ?, stand_image = ?, updated_at = ? WHERE id = ?')->execute([
            $presentationText,
            isset($_POST['ai_sourced']) ? 1 : 0,
            $imagePath,
            now(),
            $reservationId,
        ]);

        $pdo->prepare('DELETE FROM reservation_items WHERE reservation_id = ? AND item_type = ?')->execute([$reservationId, 'option']);
        if ($optionIds) {
            $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
            $optStmt = $pdo->prepare("SELECT p.* FROM products p JOIN stand_options so ON so.option_product_id = p.id WHERE so.stand_id = ? AND p.id IN ($placeholders) AND p.product_type = 'option'");
            $optStmt->execute(array_merge([(int) $reservation['stand_id']], $optionIds));
            foreach ($optStmt->fetchAll() as $option) {
                $itemInsert->execute([$reservationId, (int) $option['id'], $option['name'], 1, (float) $option['price_ht'], (float) $option['price_ttc'], 'option']);
            }
        }

        $pdo->commit();
        set_flash('success', 'Article du panier mis à jour.');
        redirect_to('/cart.php');
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', $throwable->getMessage());
        redirect_to('/cart.php');
    }
}

if ($action === 'remove') {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $reservation = get_reservation($pdo, $reservationId);
    if (!$reservation || (int) $reservation['user_id'] !== (int) $user['id'] || $reservation['status'] !== RES_CART) {
        set_flash('danger', 'Article de panier invalide.');
        redirect_to('/cart.php');
    }

    $pdo->prepare('DELETE FROM reservations WHERE id = ?')->execute([$reservationId]);
    set_flash('success', 'Article supprimé du panier et pré-réservation libérée.');
    redirect_to('/cart.php');
}

if ($action === 'confirm') {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $reservation = get_reservation($pdo, $reservationId);
    if (!$reservation || (int) $reservation['user_id'] !== (int) $user['id'] || $reservation['status'] !== RES_CART) {
        set_flash('danger', 'Réservation invalide.');
        redirect_to('/cart.php');
    }

    if (!isset($_POST['terms_accepted'])) {
        set_flash('warning', 'Vous devez accepter les CGV pour confirmer la demande.');
        redirect_to('/cart.php');
    }

    $fee = dossier_fee_for_date(today());
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM reservation_items WHERE reservation_id = ? AND item_type = ?')->execute([$reservationId, 'fee']);
    if ($fee > 0) {
        $pdo->prepare('INSERT INTO reservation_items (reservation_id, product_id, label, quantity, price_ht, price_ttc, item_type) VALUES (?, NULL, ?, 1, ?, ?, ?)')->execute([$reservationId, 'Frais de dossier non remboursables', $fee, $fee, 'fee']);
    }
    $pdo->prepare('UPDATE reservations SET status = ?, dossier_fee = ?, terms_accepted = 1, hold_expires_at = NULL, updated_at = ? WHERE id = ?')->execute([RES_PENDING, $fee, now(), $reservationId]);
    $pdo->commit();

    $admins = $pdo->query("SELECT email FROM users WHERE role_level = 0 AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminEmail) {
        queue_mail($pdo, (string) $adminEmail, 'Nouvelle demande de réservation à valider', 'Une nouvelle demande de réservation doit être validée dans le back-office KAKEMONO.');
    }
    queue_mail($pdo, $user['email'], 'Votre demande KAKEMONO a été transmise', 'Votre demande de réservation a bien été transmise à l’administration pour validation.');
    set_flash('success', 'Votre demande a été transmise à l’administration.');
    redirect_to('/profile.php');
}

redirect_to('/cart.php');
