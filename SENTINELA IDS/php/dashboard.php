<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
exigir_login();

$pagina_atual  = 'painel';
$titulo_pagina = 'Painel';
$user          = usuario_atual();

$totalMaquinasAtivas = (int) $pdo->query(
    "SELECT COUNT(*) FROM machines WHERE status IN ('online','alerta')"
)->fetchColumn();

$alertasAbertos = (int) $pdo->query(
    "SELECT COUNT(*) FROM alerts WHERE status = 'aberto'"
)->fetchColumn();

$totalAlertas = (int) $pdo->query('SELECT COUNT(*) FROM alerts')->fetchColumn();

$totalMaquinasCadastradas = (int) $pdo->query('SELECT COUNT(*) FROM machines')->fetchColumn();

// Faixa de destaque: o evento mais recente, com os detalhes da máquina.
$ultimoAlerta = $pdo->query("
    SELECT a.type, a.source_ip, a.port, a.created_at, a.status,
           m.name AS machine_name, l.name AS location_name
    FROM alerts a
    JOIN machines m ON a.machine_id = m.id
    LEFT JOIN locations l ON m.location_id = l.id
    ORDER BY a.created_at DESC
    LIMIT 1
")->fetch();

$maquinas = $pdo->query("
    SELECT m.name, m.ip, m.status, l.name AS location_name
    FROM machines m
    LEFT JOIN locations l ON m.location_id = l.id
    ORDER BY (m.status = 'alerta') DESC, m.id DESC
    LIMIT 5
")->fetchAll();

$alertasRecentes = $pdo->query("
    SELECT a.type, a.created_at, a.status, m.name AS machine_name
    FROM alerts a
    JOIN machines m ON a.machine_id = m.id
    ORDER BY a.created_at DESC
    LIMIT 6
")->fetchAll();

// --- Dados para o gráfico "Alertas por dia" (últimos 7 dias) ---
$porDiaRaw = $pdo->query("
    SELECT DATE(created_at) AS dia, COUNT(*) AS total
    FROM alerts
    WHERE created_at >= (CURDATE() - INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
")->fetchAll(PDO::FETCH_KEY_PAIR);

$diasGrafico = [];
$totaisGrafico = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i day"));
    $diasGrafico[] = date('d/m', strtotime($dia));
    $totaisGrafico[] = (int) ($porDiaRaw[$dia] ?? 0);
}

// --- Dados para o gráfico "Alertas por tipo" ---
$porTipo = $pdo->query("
    SELECT type, COUNT(*) AS total
    FROM alerts
    GROUP BY type
    ORDER BY total DESC
    LIMIT 6
")->fetchAll();
$tiposLabels = array_column($porTipo, 'type');
$tiposTotais = array_map('intval', array_column($porTipo, 'total'));

function tempo_relativo(string $data): string
{
    $diff = time() - strtotime($data);
    if ($diff < 60) return 'agora mesmo';
    if ($diff < 3600) return floor($diff / 60) . ' min atrás';
    if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
    return floor($diff / 86400) . 'd atrás';
}

require __DIR__ . '/includes/layout_top.php';
?>

<div class="greeting-head">
  <div class="greeting-title">Bem-vindo, <span><?= htmlspecialchars(explode(' ', trim($user['name']))[0]) ?></span></div>
  <div class="greeting-sub">Aqui está o resumo do monitoramento de hoje, <?= date('d/m/Y') ?>.</div>
</div>

<?php if ($naoLidas > 0): ?>
<a class="notif-banner" href="notificacoes.php">
  <span class="notif-banner-dot"></span>
  <span>Você tem <b><?= $naoLidas ?></b> <?= $naoLidas === 1 ? 'notificação não lida' : 'notificações não lidas' ?> sobre alterações no sistema.</span>
  <span class="notif-banner-go">Ver →</span>
</a>
<?php endif; ?>

<div class="stats-row">
  <div class="stat-card">
    <div class="stat-label">Máquinas ativas</div>
    <div class="stat-value"><?= $totalMaquinasAtivas ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Alertas abertos</div>
    <div class="stat-value alert"><?= $alertasAbertos ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total de alertas</div>
    <div class="stat-value violet"><?= $totalAlertas ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Máquinas cadastradas</div>
    <div class="stat-value"><?= $totalMaquinasCadastradas ?></div>
  </div>
</div>

<div class="section-title">Atividade</div>
<div class="charts-row">
  <div class="panel">
    <div class="panel-head"><div class="panel-title">Alertas por dia (últimos 7 dias)</div></div>
    <div class="panel-body"><canvas id="chart-timeline" height="130"></canvas></div>
  </div>
  <div class="panel">
    <div class="panel-head"><div class="panel-title">Alertas por tipo</div></div>
    <div class="panel-body"><canvas id="chart-types" height="130"></canvas></div>
  </div>
</div>

<div class="dash-grid">

  <div class="dash-card">
    <div class="dash-card-head">
      <div class="dash-card-title">Máquinas monitoradas</div>
      <a href="maquinas.php" class="panel-title" style="text-decoration:none; color:var(--accent);">ver todas →</a>
    </div>
    <div class="mini-list">
      <?php if (!$maquinas): ?>
        <div class="mini-empty">Nenhuma máquina cadastrada ainda.</div>
      <?php else: foreach ($maquinas as $m): ?>
        <div class="mini-item">
          <span class="dot <?= htmlspecialchars($m['status']) ?>"></span>
          <div class="mini-item-text">
            <div class="mini-item-title"><?= htmlspecialchars($m['name']) ?></div>
            <div class="mini-item-sub">
              <?= htmlspecialchars($m['ip']) ?>
              <?php if ($m['location_name']): ?> · <?= htmlspecialchars($m['location_name']) ?><?php endif; ?>
            </div>
          </div>
          <span class="mini-item-tag <?= $m['status'] ?>"><?= htmlspecialchars($m['status']) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="dash-card">
    <div class="dash-card-head">
      <div class="dash-card-title">Alertas recentes</div>
      <?php if ($alertasAbertos > 0): ?><span class="dash-card-count"><?= $alertasAbertos ?></span><?php endif; ?>
    </div>
    <div class="mini-list">
      <?php if (!$alertasRecentes): ?>
        <div class="mini-empty">Nenhum alerta registrado ainda.</div>
      <?php else: foreach ($alertasRecentes as $a): ?>
        <div class="mini-item">
          <span class="mini-ico <?= $a['status'] ?>">⚠</span>
          <div class="mini-item-text">
            <div class="mini-item-title"><?= htmlspecialchars($a['type']) ?></div>
            <div class="mini-item-sub"><?= htmlspecialchars($a['machine_name']) ?> · <?= tempo_relativo($a['created_at']) ?></div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <a href="alertas.php" class="dash-card-foot">ver todos os alertas →</a>
  </div>

</div>

<?php if ($ultimoAlerta): ?>
<div class="event-banner">
  <div>
    <div class="event-banner-label">Último evento detectado</div>
    <div class="event-banner-title"><?= htmlspecialchars($ultimoAlerta['type']) ?></div>
    <div class="event-banner-meta">
      <b><?= htmlspecialchars($ultimoAlerta['machine_name']) ?></b>
      <?php if ($ultimoAlerta['location_name']): ?> · <?= htmlspecialchars($ultimoAlerta['location_name']) ?><?php endif; ?>
      · IP <?= htmlspecialchars($ultimoAlerta['source_ip'] ?? '—') ?>
      <?php if ($ultimoAlerta['port']): ?> · porta <?= htmlspecialchars($ultimoAlerta['port']) ?><?php endif; ?>
      · <?= tempo_relativo($ultimoAlerta['created_at']) ?>
    </div>
  </div>
  <a href="alertas.php" class="btn event-banner-btn">Ver detalhes</a>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
const css       = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
const corAccent = css('--accent');
const corGrid   = css('--chart-grid');
const corTexto  = css('--chart-text');
const corSurf   = css('--surface');
Chart.defaults.font.family = css('--font');

new Chart(document.getElementById('chart-timeline'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($diasGrafico) ?>,
    datasets: [{
      label: 'Alertas',
      data: <?= json_encode($totaisGrafico) ?>,
      backgroundColor: corAccent,
      borderRadius: 8,
      borderSkipped: false,
      maxBarThickness: 38,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0, color: corTexto }, grid: { color: corGrid }, border: { display: false } },
      x: { ticks: { color: corTexto }, grid: { display: false }, border: { display: false } }
    }
  }
});

<?php if ($tiposLabels): ?>
new Chart(document.getElementById('chart-types'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($tiposLabels) ?>,
    datasets: [{
      data: <?= json_encode($tiposTotais) ?>,
      backgroundColor: [corAccent, '#fbbf24', '#f43f5e', '#a78bfa', '#38bdf8', '#78716c'],
      borderColor: corSurf,
      borderWidth: 3,
      hoverOffset: 6,
    }]
  },
  options: {
    cutout: '68%',
    plugins: { legend: { position: 'bottom', labels: { color: corTexto, boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14, font: { size: 11.5 } } } }
  }
});
<?php else: ?>
document.getElementById('chart-types').closest('.panel-body').innerHTML =
  '<div class="mini-empty">Sem dados suficientes ainda.</div>';
<?php endif; ?>
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
