<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
exigir_login();

$pagina_atual  = 'locais';
$titulo_pagina = 'Locais';

$locais = $pdo->query("
    SELECT l.id, l.name, COUNT(m.id) AS total_maquinas
    FROM locations l
    LEFT JOIN machines m ON m.location_id = l.id
    GROUP BY l.id, l.name
    ORDER BY l.name
")->fetchAll();

require __DIR__ . '/includes/layout_top.php';
?>

<div class="view-head">
  <div>
    <div class="view-title">Locais</div>
    <div class="view-sub">Setores/locais onde as máquinas monitoradas ficam.</div>
  </div>
  <div class="view-actions">
    <a href="local_form.php" class="btn">+ Novo local</a>
  </div>
</div>

<?php if (!$locais): ?>
  <div class="empty-state">Nenhum local cadastrado ainda.</div>
<?php else: ?>
  <div class="panel">
    <div class="panel-body" style="padding:0;">
      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Máquinas vinculadas</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($locais as $l): ?>
            <tr>
              <td><span class="loc-tag"><?= htmlspecialchars($l['name']) ?></span></td>
              <td><?= (int) $l['total_maquinas'] ?></td>
              <td style="text-align:right; white-space:nowrap;">
                <a class="pagehead-link" href="local_form.php?id=<?= $l['id'] ?>">editar</a>
                &nbsp;·&nbsp;
                <a class="pagehead-link" href="local_excluir.php?id=<?= $l['id'] ?>"
                   onclick="return confirm('Remover este local? As máquinas vinculadas a ele ficam sem local, mas não são apagadas.');">remover</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
