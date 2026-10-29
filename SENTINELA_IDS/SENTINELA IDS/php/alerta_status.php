<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';
exigir_login();

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare(
        'SELECT a.type, a.status, m.name AS machine_name
         FROM alerts a LEFT JOIN machines m ON a.machine_id = m.id WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $alerta = $stmt->fetch();

    if ($alerta) {
        $novo = $alerta['status'] === 'aberto' ? 'resolvido' : 'aberto';
        $pdo->prepare('UPDATE alerts SET status = ? WHERE id = ?')->execute([$novo, $id]);

        $onde = $alerta['machine_name'] ? ' (' . $alerta['machine_name'] . ')' : '';
        notificar(
            $pdo, 'alerta',
            ($novo === 'resolvido' ? 'resolveu' : 'reabriu') . ' o alerta "' . $alerta['type'] . '"' . $onde,
            'alertas.php'
        );
    }
}

// Volta para a página de onde veio (preserva busca/filtro), com fallback seguro.
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$volta   = str_contains($referer, 'alertas.php') ? $referer : 'alertas.php';

header('Location: ' . $volta);
exit;
