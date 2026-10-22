<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';
exigir_login();

$pagina_atual  = 'maquinas';
$id            = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editando      = $id !== null;
$titulo_pagina = $editando ? 'Editar máquina' : 'Ativar máquina';

$erro = '';

$maquina = [
    'name' => '', 'ip' => '', 'mac' => '', 'so' => '',
    'location_id' => '', 'status' => 'online',
];

if ($editando) {
    $stmt = $pdo->prepare('SELECT * FROM machines WHERE id = ?');
    $stmt->execute([$id]);
    $encontrada = $stmt->fetch();
    if (!$encontrada) {
        header('Location: maquinas.php');
        exit;
    }
    $maquina = $encontrada;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maquina['name']        = trim($_POST['name'] ?? '');
    $maquina['ip']          = trim($_POST['ip'] ?? '');
    $maquina['mac']         = trim($_POST['mac'] ?? '') ?: null;
    $maquina['so']          = trim($_POST['so'] ?? '') ?: null;
    $maquina['location_id'] = ($_POST['location_id'] ?? '') !== '' ? (int) $_POST['location_id'] : null;
    $maquina['status']      = $_POST['status'] ?? 'online';

    if ($maquina['name'] === '' || $maquina['ip'] === '') {
        $erro = 'Nome e IP são obrigatórios.';
    } elseif (!in_array($maquina['status'], ['online', 'alerta', 'offline'], true)) {
        $erro = 'Status inválido.';
    } else {
        try {
            if ($editando) {
                $stmt = $pdo->prepare(
                    'UPDATE machines SET name=?, ip=?, mac=?, so=?, location_id=?, status=? WHERE id=?'
                );
                $stmt->execute([
                    $maquina['name'], $maquina['ip'], $maquina['mac'], $maquina['so'],
                    $maquina['location_id'], $maquina['status'], $id,
                ]);
                notificar($pdo, 'maquina', 'editou a máquina "' . $maquina['name'] . '"', 'maquinas.php');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO machines (name, ip, mac, so, location_id, status) VALUES (?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $maquina['name'], $maquina['ip'], $maquina['mac'], $maquina['so'],
                    $maquina['location_id'], $maquina['status'],
                ]);
                notificar($pdo, 'maquina', 'ativou a máquina "' . $maquina['name'] . '"', 'maquinas.php');
            }
            header('Location: maquinas.php');
            exit;
        } catch (PDOException $e) {
            // uq_machines_ip é violada quando já existe outra máquina com o mesmo IP
            $erro = str_contains($e->getMessage(), 'uq_machines_ip')
                ? 'Já existe uma máquina cadastrada com esse IP.'
                : 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

$locais = $pdo->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();

require __DIR__ . '/includes/layout_top.php';
?>

<div class="view-head">
  <div>
    <div class="view-title"><?= $editando ? 'Editar máquina' : 'Ativar máquina' ?></div>
    <div class="view-sub">Preencha os dados da máquina a ser monitorada.</div>
  </div>
  <div class="view-actions">
    <a href="maquinas.php" class="btn-ghost">← Voltar</a>
  </div>
</div>

<div class="panel" style="max-width:480px;">
  <div class="panel-body">
    <?php if ($erro): ?>
      <div class="form-msg erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" class="modal-form">
      <div class="field">
        <label>nome da máquina</label>
        <input type="text" name="name" placeholder="VM-MONITORADA-01" required value="<?= htmlspecialchars($maquina['name']) ?>">
      </div>
      <div class="field">
        <label>local</label>
        <select name="location_id">
          <option value="">Selecione um local</option>
          <?php foreach ($locais as $l): ?>
            <option value="<?= $l['id'] ?>" <?= (string)$maquina['location_id'] === (string)$l['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($l['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>IP</label>
        <input type="text" name="ip" placeholder="192.168.56.10" required value="<?= htmlspecialchars($maquina['ip']) ?>">
      </div>
      <div class="field">
        <label>MAC (opcional)</label>
        <input type="text" name="mac" placeholder="08:00:27:1A:2B:3C" value="<?= htmlspecialchars($maquina['mac'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Sistema operacional (opcional)</label>
        <input type="text" name="so" placeholder="Windows 10 / Ubuntu..." value="<?= htmlspecialchars($maquina['so'] ?? '') ?>">
      </div>
      <div class="field">
        <label>status</label>
        <select name="status">
          <?php foreach (['online' => 'Online', 'alerta' => 'Em alerta', 'offline' => 'Offline'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= $maquina['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-actions">
        <a href="maquinas.php" class="btn-secondary" style="text-align:center; text-decoration:none;">Cancelar</a>
        <button type="submit" class="btn-primary"><?= $editando ? 'Salvar alterações' : 'Ativar máquina' ?></button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
