<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';
exigir_login();

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT name FROM machines WHERE id = ?');
    $stmt->execute([$id]);
    $nome = $stmt->fetchColumn();

    if ($nome !== false) {
        $pdo->prepare('DELETE FROM machines WHERE id = ?')->execute([$id]);
        notificar($pdo, 'maquina', 'removeu a máquina "' . $nome . '"', 'maquinas.php');
    }
}

header('Location: maquinas.php');
exit;
