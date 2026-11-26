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

$achados = json_decode($_POST['achados'] ?? '[]', true);
if (!is_array($achados)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']);
    exit;
}
$achados = array_slice($achados, 0, 512);

$localId = null;
if (($_POST['location_id'] ?? '') !== '') {
    $chk = $pdo->prepare('SELECT id FROM locations WHERE id = ?');
    $chk->execute([(int) $_POST['location_id']]);
    $localId = $chk->fetchColumn() ?: null;
}

$resultado = [];
$novos     = 0;
$existentes = 0;

foreach ($achados as $a) {
    if (!is_array($a)) continue;

    $ip = trim((string) ($a['ip'] ?? ''));
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        continue;
    }

    $mac = strtoupper(str_replace('-', ':', trim((string) ($a['mac'] ?? ''))));
    if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
        $mac = null;
    }

    $host = trim((string) ($a['host'] ?? ''));
    if ($host === '' || $host === '(desconhecido)') {
        $host = null;
    } else {
        $host = substr($host, 0, 255);
    }

    $so = trim((string) ($a['so'] ?? ''));
    $so = $so !== '' ? substr($so, 0, 100) : null;

    $existente = null;
    if ($mac !== null) {
        $s = $pdo->prepare('SELECT id, hostname, mac, so FROM machines WHERE mac = ? LIMIT 1');
        $s->execute([$mac]);
        $existente = $s->fetch() ?: null;
    }
    if ($existente === null) {
        $s = $pdo->prepare('SELECT id, hostname, mac, so FROM machines WHERE ip = ? LIMIT 1');
        $s->execute([$ip]);
        $existente = $s->fetch() ?: null;
    }

    if ($existente !== null) {
        $sets = []; $vals = [];
        if ($host !== null && ($existente['hostname'] ?? '') === '') { $sets[] = 'hostname = ?'; $vals[] = $host; }
        if ($mac  !== null && ($existente['mac']      ?? '') === '') { $sets[] = 'mac = ?';      $vals[] = $mac;  }
        if ($so   !== null && ($existente['so']       ?? '') === '') { $sets[] = 'so = ?';       $vals[] = $so;   }
        if ($sets) {
            $vals[] = $existente['id'];
            $pdo->prepare('UPDATE machines SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        }
        $existentes++;
        $resultado[] = ['ip' => $ip, 'situacao' => 'existente'];
        continue;
    }

    $nome = $host !== null
        ? strtoupper(explode('.', $host)[0])
        : 'PC-' . str_replace('.', '-', $ip);
    $nome = substr($nome !== '' ? $nome : 'PC-' . str_replace('.', '-', $ip), 0, 100);

    try {
        $pdo->prepare(
            'INSERT INTO machines (name, ip, hostname, mac, so, location_id, status) VALUES (?,?,?,?,?,?,?)'
        )->execute([$nome, $ip, $host, $mac, $so, $localId, 'online']);
        $novos++;
        $resultado[] = ['ip' => $ip, 'situacao' => 'novo'];
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            $existentes++;
            $resultado[] = ['ip' => $ip, 'situacao' => 'existente'];
        } else {
            error_log('Sentinela: falha no cadastro automático: ' . $e->getMessage());
            $resultado[] = ['ip' => $ip, 'situacao' => 'erro'];
        }
    }
}

if ($novos > 0) {
    notificar(
        $pdo, 'maquina',
        'cadastrou ' . $novos . ($novos === 1 ? ' máquina detectada' : ' máquinas detectadas') . ' na rede',
        'cadastro_maquinas.php',
        ['id' => null, 'name' => 'Cadastro automático']
    );
}

echo json_encode([
    'ok'         => true,
    'novos'      => $novos,
    'existentes' => $existentes,
    'itens'      => $resultado,
]);
