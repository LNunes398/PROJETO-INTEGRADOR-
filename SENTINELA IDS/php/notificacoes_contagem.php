<?php
/** Endpoint leve consultado pela barra de navegação (JSON). */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!usuario_logado()) {
    http_response_code(401);
    echo json_encode(['erro' => 'não autenticado']);
    exit;
}

$user   = usuario_atual();
$ultimo = max(0, (int) ($_GET['ultimo'] ?? 0));

$stmt = $pdo->prepare(
    'SELECT id, actor_id, actor_name, message FROM notifications
     WHERE user_id = ? AND id > ? AND is_read = 0 ORDER BY id ASC LIMIT 3'
);
$stmt->execute([$user['id'], $ultimo]);
$novas = [];
foreach ($stmt->fetchAll() as $n) {
    $novas[] = ['id' => (int) $n['id'], 'texto' => texto_notificacao($n, $user['id'])];
}

$stmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?');
$stmt->execute([$user['id']]);

echo json_encode([
    'nao_lidas' => contar_nao_lidas($pdo, $user['id']),
    'ultimo_id' => (int) $stmt->fetchColumn(),
    'novas'     => $novas,
]);
