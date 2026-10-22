<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
exigir_login();

$pagina_atual  = 'maquinas';
$titulo_pagina = 'Máquinas';

$busca = trim($_GET['q'] ?? '');

$sql = "SELECT m.*, l.name AS location_name
        FROM machines m
        LEFT JOIN locations l ON m.location_id = l.id";
$params = [];

if ($busca !== '') {
    $sql .= " WHERE m.name LIKE ? OR m.ip LIKE ?";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " ORDER BY m.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$maquinas = $stmt->fetchAll();

require __DIR__ . '/includes/layout_top.php';
?>

<div class="view-head">
  <div>
    <div class="view-title">Máquinas</div>
    <div class="view-sub">Máquinas monitoradas pelo Sentinela.</div>
  </div>
  <div class="view-actions">
    <a href="maquina_form.php" class="btn">+ Ativar máquina</a>
  </div>
</div>

<div class="toolbar">
  <form method="get" style="flex:1; display:flex;">
    <input type="text" name="q" class="search-input" placeholder="Buscar por nome ou IP..." value="<?= htmlspecialchars($busca) ?>">
  </form>
</div>

<?php if (!$maquinas): ?>
  <div class="empty-state">
    Nenhuma máquina cadastrada ainda.<br>
    Clique em "+ Ativar máquina" para começar a monitorar.
  </div>
<?php else: ?>
  <div class="machines-grid">
    <?php foreach ($maquinas as $m): ?>
      <div class="machine-card <?= $m['status'] === 'alerta' ? 'is-alert' : '' ?>">
        <div class="machine-card-top">
          <div class="machine-card-name"><?= htmlspecialchars($m['name']) ?></div>
          <div class="dot <?= htmlspecialchars($m['status']) ?>"></div>
        </div>
        <div class="machine-card-loc">
          <?php if ($m['location_name']): ?>
            <span class="loc-tag"><?= htmlspecialchars($m['location_name']) ?></span>
          <?php endif; ?>
        </div>
        <div class="machine-card-meta">
          <b>IP:</b> <?= htmlspecialchars($m['ip']) ?><br>
          <b>MAC:</b> <?= htmlspecialchars($m['mac'] ?? '—') ?><br>
          <b>SO:</b> <?= htmlspecialchars($m['so'] ?? '—') ?><br>
          <b>Status:</b> <?= htmlspecialchars($m['status']) ?>
        </div>
        <div class="machine-card-actions">
          <a class="icon-btn" href="maquina_form.php?id=<?= $m['id'] ?>">✏️ Editar</a>
          <a class="icon-btn danger" href="maquina_excluir.php?id=<?= $m['id'] ?>"
             onclick="return confirm('Remover esta máquina e todos os alertas ligados a ela?');">🗑️ Remover</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
