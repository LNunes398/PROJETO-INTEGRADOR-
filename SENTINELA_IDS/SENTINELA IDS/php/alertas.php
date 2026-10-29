<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/ataques.php';
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
    <button type="button" class="btn-ghost" id="abrir-guia">Guia de ataques</button>
    <a class="btn-ghost" href="alertas_exportar.php?<?= http_build_query(array_filter(['q' => $busca, 'status' => $status])) ?>">Exportar CSV</a>
  </div>
</div>

<div class="toolbar">
  <form method="get" style="flex:1; display:flex;">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <input type="text" name="q" class="search-input" placeholder="Buscar por IP, máquina ou ataque..." value="<?= htmlspecialchars($busca) ?>">
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
          <th>Ataque</th>
          <th>Pacotes</th>
          <th>Porta</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$alertas): ?>
          <tr class="empty-row"><td colspan="9">Nenhum alerta encontrado.</td></tr>
        <?php else: foreach ($alertas as $a): ?>
          <?php $k = ataque_chave($a['type']); ?>
          <tr class="alerta-row" data-id="<?= $a['id'] ?>" style="cursor:pointer;">
            <td><?= htmlspecialchars($a['created_at']) ?></td>
            <td><?= htmlspecialchars($a['machine_name']) ?></td>
            <td><?= $a['location_name'] ? '<span class="loc-tag">' . htmlspecialchars($a['location_name']) . '</span>' : '—' ?></td>
            <td><?= htmlspecialchars($a['source_ip'] ?? '—') ?></td>
            <td><span class="atk-name"><?= htmlspecialchars(ataque_nome($a['type'])) ?></span></td>
            <td><?= (int) $a['syn_count'] ?></td>
            <td><?= htmlspecialchars($a['port'] ?? '—') ?></td>
            <td><span class="badge <?= $a['status'] ?>"><?= $a['status'] ?></span></td>
            <td>
              <a class="pagehead-link" href="alerta_status.php?id=<?= $a['id'] ?>" onclick="event.stopPropagation();">
                <?= $a['status'] === 'aberto' ? 'marcar resolvido' : 'reabrir' ?>
              </a>
              <template id="tpl-<?= $a['id'] ?>">
                <div class="kv"><span class="kv-k">Ataque detectado</span><span class="kv-v"><span class="atk-name"><?= htmlspecialchars(ataque_nome($a['type'])) ?></span></span></div>
                <div class="kv"><span class="kv-k">Data / horário</span><span class="kv-v"><?= htmlspecialchars($a['created_at']) ?></span></div>
                <div class="kv"><span class="kv-k">IP de origem</span><span class="kv-v"><?= htmlspecialchars($a['source_ip'] ?? '—') ?></span></div>
                <div class="kv"><span class="kv-k">MAC de origem</span><span class="kv-v"><?= htmlspecialchars($a['source_mac'] ?? '—') ?></span></div>
                <div class="kv"><span class="kv-k">SO de origem</span><span class="kv-v"><?= htmlspecialchars($a['source_so'] ?? '—') ?></span></div>
                <div class="kv"><span class="kv-k">Máquina de destino</span><span class="kv-v"><?= htmlspecialchars($a['machine_name']) ?></span></div>
                <div class="kv"><span class="kv-k">Porta</span><span class="kv-v"><?= htmlspecialchars($a['port'] ?? '—') ?></span></div>
                <div class="kv"><span class="kv-k">Pacotes SYN</span><span class="kv-v"><?= (int) $a['syn_count'] ?></span></div>
                <div class="kv"><span class="kv-k">Status</span><span class="kv-v"><span class="badge <?= $a['status'] ?>"><?= $a['status'] ?></span></span></div>
                <?php if ($k): ?>
                  <button type="button" class="btn-secondary atk-toggle">Entender este ataque</button>
                  <div class="atk-explain" style="display:none;"><?= ataque_explicacao_html(ataques_info()[$k], $a) ?></div>
                <?php endif; ?>
                <div class="modal-actions">
                  <a class="btn-primary" style="text-align:center; text-decoration:none;" href="alerta_status.php?id=<?= $a['id'] ?>"><?= $a['status'] === 'aberto' ? 'Marcar como resolvido' : 'Reabrir alerta' ?></a>
                </div>
              </template>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<template id="tpl-guia">
  <div class="modal-note">Tipos de ataque que o Sentinela identifica. Abra cada um para ver como é o comportamento normal e como o ataque se diferencia dele.</div>
  <?php foreach (ataques_info() as $inf): ?>
    <details class="atk-item"><summary><?= htmlspecialchars($inf['nome']) ?></summary><?= ataque_explicacao_html($inf) ?></details>
  <?php endforeach; ?>
</template>

<div id="modal-backdrop"><div class="modal wide" id="modal"></div></div>

<script>
(function () {
  var backdrop = document.getElementById('modal-backdrop');
  var modal    = document.getElementById('modal');

  function abrir(titulo, tplId) {
    var tpl = document.getElementById(tplId);
    modal.innerHTML = '<div class="modal-head"><div class="modal-title"></div>' +
      '<button type="button" class="modal-close">&times;</button></div><div class="modal-body"></div>';
    modal.querySelector('.modal-title').textContent = titulo;
    modal.querySelector('.modal-body').appendChild(tpl.content.cloneNode(true));
    modal.querySelector('.modal-close').onclick = fechar;
    var btn = modal.querySelector('.atk-toggle');
    if (btn) btn.onclick = function () {
      var box = modal.querySelector('.atk-explain');
      var aberto = box.style.display !== 'none';
      box.style.display = aberto ? 'none' : '';
      btn.textContent = aberto ? 'Entender este ataque' : 'Esconder explicação';
    };
    backdrop.classList.add('active');
  }
  function fechar() { backdrop.classList.remove('active'); }

  backdrop.addEventListener('click', function (e) { if (e.target === backdrop) fechar(); });
  document.getElementById('abrir-guia').addEventListener('click', function () { abrir('Guia de ataques', 'tpl-guia'); });
  document.querySelectorAll('.alerta-row').forEach(function (tr) {
    tr.addEventListener('click', function () { abrir('Detalhes do alerta', 'tpl-' + tr.dataset.id); });
  });
})();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
