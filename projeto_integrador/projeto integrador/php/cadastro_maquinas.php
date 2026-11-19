<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
exigir_login();

$pagina_atual  = 'cadastro';
$titulo_pagina = 'Cadastro';

require_once __DIR__ . '/includes/cadastro_lista.php';

$filtroLo = is_string($_GET['local'] ?? '') ? $_GET['local'] : '';
$busca    = is_string($_GET['q'] ?? '') ? trim($_GET['q']) : '';
$locais   = $pdo->query('SELECT id, name FROM locations ORDER BY name')->fetchAll();

$sql = "SELECT m.*, l.name AS location_name
        FROM machines m LEFT JOIN locations l ON m.location_id = l.id";
$cond = []; $params = [];
if ($busca !== '') {
    $cond[] = '(m.name LIKE ? OR m.ip LIKE ? OR m.hostname LIKE ? OR m.mac LIKE ?)';
    array_push($params, "%$busca%", "%$busca%", "%$busca%", "%$busca%");
}
if ($filtroLo === 'none') {
    $cond[] = 'm.location_id IS NULL';
} elseif ($filtroLo !== '' && ctype_digit((string) $filtroLo)) {
    $cond[] = 'm.location_id = ?';
    $params[] = (int) $filtroLo;
}
if ($cond) $sql .= ' WHERE ' . implode(' AND ', $cond);

$sql .= ' ORDER BY (m.location_id IS NULL) DESC, l.name, m.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$maquinas = $stmt->fetchAll();

if (isset($_GET['parcial'])) {
    cm_lista($maquinas, $locais);
    exit;
}

require __DIR__ . '/includes/layout_top.php';
?>

<div id="cadastro-app" data-csrf="<?= cm_h(csrf_token()) ?>"></div>

<div class="view-head">
  <div>
    <div class="view-title">Cadastro de computadores</div>
    <div class="view-sub">Cadastre manualmente ou deixe o Sentinela identificar os computadores da rede, e organize tudo por local.</div>
  </div>
</div>

<div class="cm-tabs">
  <button type="button" class="cm-tab on" data-aba="manual">✍️ Cadastro manual</button>
  <button type="button" class="cm-tab" data-aba="auto">📡 Cadastro automático</button>
</div>

<div class="cm-pane on panel" id="pane-manual">
  <div class="panel-body">
    <div class="modal-note">Informe nome, IP, hostname, MAC, sistema operacional e <b>local</b> do computador. O formulário é o mesmo de sempre.</div>
    <a href="maquina_form.php" class="btn">+ Cadastrar computador manualmente</a>
  </div>
</div>

<div class="cm-pane panel" id="pane-auto">
  <div class="panel-body">
    <div class="modal-note" id="sc-status">Conectando ao scanner…</div>

    <div class="cm-row">
      <button type="button" class="btn" data-cmd="iniciar">▶ Iniciar busca</button>
      <button type="button" class="btn-ghost" data-cmd="parar">⏹ Parar</button>
      <button type="button" class="btn-ghost" data-cmd="continuar">⏯ Continuar de onde parou</button>
      <button type="button" class="btn-ghost" data-cmd="reiniciar">↺ Zerar</button>
      <progress id="sc-bar" class="cm-bar" max="254" value="0"></progress>
    </div>

    <div class="cm-row">
      <label><input type="checkbox" id="opt-auto" checked> Cadastrar automaticamente o que for encontrado</label>
      <label><input type="checkbox" id="opt-cont"> Busca contínua, repetindo a cada
        <select id="opt-min" class="cm-sel">
          <option value="1">1 min</option><option value="5" selected>5 min</option>
          <option value="15">15 min</option><option value="60">60 min</option>
        </select></label>
    </div>

    <div class="cm-row">
      <label for="opt-local">Local dos computadores novos:</label>
      <select id="opt-local" class="cm-sel">
        <option value="">— Não definido (pendente) —</option>
        <?php foreach ($locais as $l): ?>
          <option value="<?= (int) $l['id'] ?>"><?= cm_h($l['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn-ghost" id="btn-agora">＋ Cadastrar detectados agora</button>
    </div>
    <div id="cm-msg"></div>

    <div class="cm-scroll">
      <table class="cm-table">
        <thead><tr><th>IP</th><th>MAC</th><th>Sistema operacional</th><th>Hostname</th><th>Situação</th></tr></thead>
        <tbody id="sc-tb"><tr><td colspan="5" style="text-align:center;color:var(--text-faint)">Nenhum computador encontrado ainda.</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><div class="panel-title">Computadores cadastrados</div></div>
  <div class="panel-body">
    <form method="get" class="cm-row" id="form-filtro">
      <input type="text" name="q" class="search-input" style="flex:1; min-width:200px;" placeholder="Buscar por nome, IP, hostname ou MAC..." value="<?= cm_h($busca) ?>">
      <select name="local" class="cm-sel" onchange="this.form.submit()">
        <option value="">Todos os locais</option>
        <option value="none" <?= $filtroLo === 'none' ? 'selected' : '' ?>>Local pendente / não definido</option>
        <?php foreach ($locais as $l): ?>
          <option value="<?= (int) $l['id'] ?>" <?= (string) $filtroLo === (string) $l['id'] ? 'selected' : '' ?>><?= cm_h($l['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-ghost">Filtrar</button>
    </form>
    <div class="cm-scroll" id="lista"><?php cm_lista($maquinas, $locais); ?></div>
  </div>
</div>

<script src="js/cadastro.js"></script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
