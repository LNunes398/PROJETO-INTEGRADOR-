<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';
exigir_login();

$pagina_atual  = 'notificacoes';
$titulo_pagina = 'Notificações';
$user          = usuario_atual();

$filtro = $_GET['f'] ?? 'nao_lidas';
if (!in_array($filtro, ['nao_lidas', 'lidas', 'todas'], true)) {
    $filtro = 'nao_lidas';
}

$where = match ($filtro) {
    'nao_lidas' => 'AND is_read = 0',
    'lidas'     => 'AND is_read = 1',
    default     => '',
};

$stmt = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id = ? $where ORDER BY created_at DESC, id DESC LIMIT 200"
);
$stmt->execute([$user['id']]);
$notificacoes = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT SUM(is_read = 0) AS nao_lidas, SUM(is_read = 1) AS lidas FROM notifications WHERE user_id = ?');
$stmt->execute([$user['id']]);
$cont = $stmt->fetch();
$qtdNaoLidas = (int) ($cont['nao_lidas'] ?? 0);
$qtdLidas    = (int) ($cont['lidas'] ?? 0);

require __DIR__ . '/includes/layout_top.php';
?>

<div class="view-head">
  <div>
    <div class="view-title">Notificações</div>
    <div class="view-sub">Tudo o que mudou no sistema. Marque como lida e, depois, apague de vez.</div>
  </div>
  <div class="view-actions">
    <?php if ($qtdNaoLidas > 0): ?>
      <form method="post" action="notificacao_acao.php" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="ler_todas">
        <input type="hidden" name="f" value="<?= htmlspecialchars($filtro) ?>">
        <button type="submit" class="btn-ghost">✓ Marcar todas como lidas</button>
      </form>
    <?php endif; ?>
    <?php if ($qtdLidas > 0): ?>
      <form method="post" action="notificacao_acao.php" style="display:inline;"
            onsubmit="return confirm('Apagar permanentemente todas as notificações lidas?');">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="apagar_lidas">
        <input type="hidden" name="f" value="<?= htmlspecialchars($filtro) ?>">
        <button type="submit" class="btn-ghost">🗑 Apagar lidas</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="toolbar">
  <a class="filter-chip <?= $filtro === 'nao_lidas' ? 'active' : '' ?>" href="notificacoes.php?f=nao_lidas">Não lidas (<?= $qtdNaoLidas ?>)</a>
  <a class="filter-chip <?= $filtro === 'lidas' ? 'active' : '' ?>" href="notificacoes.php?f=lidas">Lidas (<?= $qtdLidas ?>)</a>
  <a class="filter-chip <?= $filtro === 'todas' ? 'active' : '' ?>" href="notificacoes.php?f=todas">Todas</a>
</div>

<?php if (!$notificacoes): ?>
  <div class="empty-state">
    <?php if ($filtro === 'nao_lidas'): ?>
      Nenhuma notificação nova. Quando alguém alterar algo no sistema, aparece aqui.
    <?php elseif ($filtro === 'lidas'): ?>
      Nenhuma notificação lida guardada.
    <?php else: ?>
      Você não tem notificações.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="notif-list">
    <?php foreach ($notificacoes as $n): ?>
      <?php $lida = (int) $n['is_read'] === 1; ?>
      <div class="notif-item <?= $lida ? 'read' : 'unread' ?>">
        <div class="mini-ico"><?= icone_notificacao($n['type']) ?></div>
        <div class="notif-body">
          <div class="notif-text">
            <?php if ($n['link']): ?>
              <a href="<?= htmlspecialchars($n['link']) ?>"><?= htmlspecialchars(texto_notificacao($n, $user['id'])) ?></a>
            <?php else: ?>
              <?= htmlspecialchars(texto_notificacao($n, $user['id'])) ?>
            <?php endif; ?>
          </div>
          <div class="notif-meta">
            <?= htmlspecialchars(tempo_atras($n['created_at'])) ?>
            <?php if ($lida && $n['read_at']): ?> · lida <?= htmlspecialchars(tempo_atras($n['read_at'])) ?><?php endif; ?>
          </div>
        </div>
        <div class="notif-actions">
          <form method="post" action="notificacao_acao.php">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
            <input type="hidden" name="f" value="<?= htmlspecialchars($filtro) ?>">
            <?php if (!$lida): ?>
              <input type="hidden" name="acao" value="ler">
              <button type="submit" class="icon-btn">Marcar como lida</button>
            <?php else: ?>
              <input type="hidden" name="acao" value="apagar">
              <button type="submit" class="icon-btn danger"
                      onclick="return confirm('Apagar esta notificação permanentemente?');">Apagar</button>
            <?php endif; ?>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
