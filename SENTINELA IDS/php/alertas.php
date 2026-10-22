<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
exigir_login();

$pagina_atual  = 'alertas';
$titulo_pagina = 'Alertas';

$busca  = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'todos'; // todos | aberto | resolvido

$sql = "SELECT a.*, m.name AS machine_name, l.name AS location_name
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

function link_filtro(string $statusAlvo, string $busca, string $atual, string $label): string
{
    $q = http_build_query(array_filter(['q' => $busca, 'status' => $statusAlvo]));
    $ativo = $atual === $statusAlvo ? 'active' : '';
    return "<a class=\"filter-chip $ativo\" href=\"alertas.php?$q\">$label</a>";
}

require __DIR__ . '/includes/layout_top.php';
?>

<div class="view-head">
  <div>
    <div class="view-title">Alertas</div>
    <div class="view-sub">Histórico completo de eventos detectados.</div>
  </div>
  <div class="view-actions">
    <a class="btn-ghost" href="alertas_exportar.php?<?= http_build_query(array_filter(['q' => $busca, 'status' => $status])) ?>">Exportar CSV</a>
  </div>
</div>

<div class="toolbar">
  <form method="get" style="flex:1; display:flex;">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <input type="text" name="q" class="search-input" placeholder="Buscar por IP, máquina ou tipo..." value="<?= htmlspecialchars($busca) ?>">
  </form>
  <?= link_filtro('todos', $busca, $status, 'Todos') ?>
  <?= link_filtro('aberto', $busca, $status, 'Abertos') ?>
  <?= link_filtro('resolvido', $busca, $status, 'Resolvidos') ?>
</div>

<div class="panel alerts-panel">
  <div class="panel-head">
    <div class="panel-title">Registros</div>
    <div class="panel-title"><?= count($alertas) ?> registro(s)</div>
  </div>
  <div class="panel-body">
    <table>
      <thead>
        <tr>
          <th>Quando</th>
          <th>Máquina</th>
          <th>Local</th>
          <th>IP origem</th>
          <th>Tipo</th>
          <th>Porta</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$alertas): ?>
          <tr class="empty-row"><td colspan="8">Nenhum alerta encontrado.</td></tr>
        <?php else: foreach ($alertas as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['created_at']) ?></td>
            <td><?= htmlspecialchars($a['machine_name']) ?></td>
            <td><?= $a['location_name'] ? '<span class="loc-tag">' . htmlspecialchars($a['location_name']) . '</span>' : '—' ?></td>
            <td><?= htmlspecialchars($a['source_ip'] ?? '—') ?></td>
            <td><?= htmlspecialchars($a['type']) ?></td>
            <td><?= htmlspecialchars($a['port'] ?? '—') ?></td>
            <td><span class="badge <?= $a['status'] ?>"><?= $a['status'] ?></span></td>
            <td>
              <a class="pagehead-link" href="alerta_status.php?id=<?= $a['id'] ?>">
                <?= $a['status'] === 'aberto' ? 'marcar resolvido' : 'reabrir' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
