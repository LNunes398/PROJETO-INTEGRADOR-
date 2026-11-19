<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';
exigir_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método inválido.']);
    exit;
}
csrf_validar();

$id = (int) ($_POST['id'] ?? 0);
$st = $pdo->prepare('SELECT name FROM machines WHERE id = ?');
$st->execute([$id]);
$nome = $st->fetchColumn();
if ($nome === false) {
    echo json_encode(['ok' => false, 'erro' => 'Máquina não encontrada.']);
    exit;
}

$localId = null; $localNome = null;
if (($_POST['location_id'] ?? '') !== '') {
    $st = $pdo->prepare('SELECT id, name FROM locations WHERE id = ?');
    $st->execute([(int) $_POST['location_id']]);
    $l = $st->fetch();
    if (!$l) {
        echo json_encode(['ok' => false, 'erro' => 'Local inválido.']);
        exit;
    }
    $localId = (int) $l['id']; $localNome = $l['name'];
}

$pdo->prepare('UPDATE machines SET location_id = ? WHERE id = ?')->execute([$localId, $id]);

notificar(
    $pdo, 'maquina',
    $localNome !== null
        ? 'definiu o local da máquina "' . $nome . '" como "' . $localNome . '"'
        : 'removeu o local da máquina "' . $nome . '"',
    'cadastro_maquinas.php'
);

echo json_encode(['ok' => true]);
