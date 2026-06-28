<?php
require_once __DIR__ . '/../includes/layout.php';
require_roles([ROLE_ADMIN]);
$users = $pdo->query('SELECT u.*, (SELECT COUNT(*) FROM reservations r WHERE r.user_id = u.id) AS reservation_count FROM users u ORDER BY u.created_at DESC')->fetchAll();
render_header('Utilisateurs', 'users_admin');
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h2 section-title mb-1">Comptes utilisateurs</h1><p class="text-muted mb-0">Validation, rôles, historique de connexions et synthèse des factures.</p></div></div>
<div class="card rounded-4 bg-white"><div class="card-body p-4"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th>Réservations</th><th></th></tr></thead><tbody><?php foreach ($users as $item): ?><tr><td><?= e($item['first_name'] . ' ' . $item['last_name']) ?></td><td><?= e($item['email']) ?></td><td><?= e(role_label((int) $item['role_level'])) ?></td><td><?= e($item['status']) ?></td><td><?= (int) $item['reservation_count'] ?></td><td><a class="btn btn-sm btn-outline-primary" href="/admin/user_edit.php?id=<?= (int) $item['id'] ?>">Gérer</a></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php render_footer(); ?>
