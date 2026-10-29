<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';
exigir_login();

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT name FROM locations WHERE id = ?');
    $stmt->execute([$id]);
    $nome = $stmt->fetchColumn();

    if ($nome !== false) {
        $pdo->prepare('DELETE FROM locations WHERE id = ?')->execute([$id]);
        notificar($pdo, 'local', 'removeu o local "' . $nome . '"', 'locais.php');
    }
}

header('Location: locais.php');
exit;
