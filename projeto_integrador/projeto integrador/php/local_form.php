<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';
exigir_login();

$pagina_atual = 'locais';
$id           = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editando     = $id !== null;
$titulo_pagina = $editando ? 'Editar local' : 'Novo local';

$erro = '';
$nome = '';

if ($editando) {
    $stmt = $pdo->prepare('SELECT * FROM locations WHERE id = ?');
    $stmt->execute([$id]);
    $encontrado = $stmt->fetch();
    if (!$encontrado) {
        header('Location: locais.php');
        exit;
    }
    $nome = $encontrado['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['name'] ?? '');

    if ($nome === '') {
        $erro = 'Informe o nome do local.';
    } else {
        try {
            if ($editando) {
                $stmt = $pdo->prepare('UPDATE locations SET name = ? WHERE id = ?');
                $stmt->execute([$nome, $id]);
                notificar($pdo, 'local', 'renomeou um local para "' . $nome . '"', 'locais.php');
            } else {
                $stmt = $pdo->prepare('INSERT INTO locations (name) VALUES (?)');
                $stmt->execute([$nome]);
                notificar($pdo, 'local', 'criou o local "' . $nome . '"', 'locais.php');
            }
            header('Location: locais.php');
            exit;
        } catch (PDOException $e) {
            $erro = str_contains($e->getMessage(), 'Duplicate')
                ? 'Já existe um local com esse nome.'
                : 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

require __DIR__ . '/includes/layout_top.php';
?>

<div class="view-head">
  <div>
    <div class="view-title"><?= $editando ? 'Editar local' : 'Novo local' ?></div>
    <div class="view-sub">Setores usados para agrupar as máquinas monitoradas.</div>
  </div>
  <div class="view-actions">
    <a href="locais.php" class="btn-ghost">← Voltar</a>
  </div>
</div>

<div class="panel" style="max-width:420px;">
  <div class="panel-body">
    <?php if ($erro): ?>
      <div class="form-msg erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" class="modal-form">
      <div class="field">
        <label>Nome do local</label>
        <input type="text" name="name" placeholder="Ex: Laboratório de Informática" required value="<?= htmlspecialchars($nome) ?>">
      </div>
      <div class="modal-actions">
        <a href="locais.php" class="btn-secondary" style="text-align:center; text-decoration:none;">Cancelar</a>
        <button type="submit" class="btn-primary"><?= $editando ? 'Salvar alterações' : 'Criar local' ?></button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
