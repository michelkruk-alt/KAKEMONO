<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();

if (is_privileged($user)) {
    $contacts = $pdo->query('SELECT id, first_name, last_name, email FROM users WHERE id != ' . (int) $user['id'] . ' ORDER BY last_name, first_name')->fetchAll();
} else {
    $contacts = $pdo->query('SELECT id, first_name, last_name, email FROM users WHERE role_level IN (0, 3) AND status = "active" ORDER BY role_level, last_name')->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $recipientId = (int) ($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $allowedRecipientIds = array_map(static fn(array $contact): int => (int) $contact['id'], $contacts ?? []);
    if ($recipientId && in_array($recipientId, $allowedRecipientIds, true) && $subject !== '' && $body !== '') {
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, recipient_id, subject, body, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([(int) $user['id'], $recipientId, $subject, $body, now()]);
        set_flash('success', 'Message envoyé.');
        redirect_to('/messages.php?recipient=' . $recipientId);
    }
}
$recipientId = (int) ($_GET['recipient'] ?? ($contacts[0]['id'] ?? 0));
$conversation = [];
if ($recipientId) {
    $stmt = $pdo->prepare(<<<'SQL'
        SELECT m.*, s.first_name AS sender_first, s.last_name AS sender_last
        FROM messages m
        JOIN users s ON s.id = m.sender_id
        WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
        ORDER BY created_at ASC
    SQL);
    $stmt->execute([(int) $user['id'], $recipientId, $recipientId, (int) $user['id']]);
    $conversation = $stmt->fetchAll();
}
render_header('Messagerie', 'messages');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4">
                <h1 class="h4 section-title">Contacts</h1>
                <div class="list-group mt-3">
                    <?php foreach ($contacts as $contact): ?>
                        <a class="list-group-item list-group-item-action <?= $recipientId === (int) $contact['id'] ? 'active' : '' ?>" href="/messages.php?recipient=<?= (int) $contact['id'] ?>"><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?><br><small><?= e($contact['email']) ?></small></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card rounded-4 bg-white">
            <div class="card-body p-4">
                <h2 class="h4 section-title mb-3">Conversation</h2>
                <div class="vstack gap-3 mb-4" style="max-height: 420px; overflow:auto;">
                    <?php foreach ($conversation as $message): ?>
                        <div class="soft-panel <?= (int) $message['sender_id'] === (int) $user['id'] ? 'border border-primary' : '' ?>">
                            <div class="d-flex justify-content-between gap-2"><strong><?= e($message['subject']) ?></strong><small class="text-muted"><?= e($message['created_at']) ?></small></div>
                            <div class="small text-muted mb-2"><?= e($message['sender_first'] . ' ' . $message['sender_last']) ?></div>
                            <p class="mb-0"><?= nl2br(e($message['body'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$conversation): ?><p class="text-muted mb-0">Aucun échange pour le moment.</p><?php endif; ?>
                </div>
                <?php if ($recipientId): ?>
                    <form method="post" class="vstack gap-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="recipient_id" value="<?= $recipientId ?>">
                        <div><label class="form-label">Sujet</label><input name="subject" class="form-control" required></div>
                        <div><label class="form-label">Message</label><textarea name="body" rows="5" class="form-control" required></textarea></div>
                        <button class="btn btn-primary align-self-start">Envoyer</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
