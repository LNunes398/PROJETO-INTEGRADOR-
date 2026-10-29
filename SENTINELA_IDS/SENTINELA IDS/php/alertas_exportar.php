<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
exigir_login();

$busca  = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'todos';

$sql = "SELECT a.created_at, m.name AS machine_name, l.name AS location_name,
               a.source_ip, a.source_mac, a.dest_ip, a.type, a.port, a.syn_count, a.status
        FROM alerts a
        JOIN machines m ON a.machine_id = m.id
        LEFT JOIN locations l ON m.location_id = l.id
        WHERE 1=1";
$params = [];

if ($busca !== '') {
    $sql .= " AND (a.source_ip LIKE ? OR m.name LIKE ? OR a.type LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if (in_array($status, ['aberto', 'resolvido'], true)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alertas = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="alertas_sentinela_' . date('Y-m-d_His') . '.csv"');

$saida = fopen('php://output', 'w');
fprintf($saida, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM, pro Excel abrir acentos certinho

fputcsv($saida, ['Quando', 'Máquina', 'Local', 'IP origem', 'MAC origem', 'IP destino', 'Ataque', 'Porta', 'Pacotes SYN', 'Status']);

foreach ($alertas as $a) {
    fputcsv($saida, [
        $a['created_at'], $a['machine_name'], $a['location_name'] ?? '',
        $a['source_ip'], $a['source_mac'], $a['dest_ip'],
        $a['type'], $a['port'], $a['syn_count'], $a['status'],
    ]);
}

fclose($saida);
exit;
