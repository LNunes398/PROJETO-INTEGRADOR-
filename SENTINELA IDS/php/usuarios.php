<?php
/**
 * Usuários — cada pessoa só enxerga e altera a PRÓPRIA conta.
 * Primeiro escolhe o que fazer:
 *   (sem parâmetro)   -> escolha: Visualizar / Editar / Excluir conta
 *   ?acao=visualizar  -> mostra os dados, sem permitir alterar
 *   ?acao=editar      -> formulário "Editar Conta"
 *   ?acao=excluir     -> tela de confirmação para excluir a conta
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';
exigir_login();

$pagina_atual  = 'usuarios';
$titulo_pagina = 'Usuários';
$uid           = usuario_atual()['id'];

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$uid]);
$conta = $stmt->fetch();
if (!$conta) { // conta não existe mais (excluída em outra aba/sessão)
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}
if (empty($conta['uuid'])) { // contas antigas ganham a identificação interna
    $conta['uuid'] = gerar_uuid();
    $pdo->prepare('UPDATE users SET uuid = ? WHERE id = ?')->execute([$conta['uuid'], $uid]);
}

$acao = $_GET['acao'] ?? '';
if (!in_array($acao, ['visualizar', 'editar', 'excluir'], true)) {
    $acao = '';
}

$erro        = '';
$ok          = ($_GET['ok'] ?? '') === '1' ? 'Dados atualizados com sucesso.' : '';
$erroExcluir = '';
$form = [
    'name'      => $conta['name'] ?? '',
    'last_name' => $conta['last_name'] ?? '',
    'username'  => $conta['username'],
    'email'     => $conta['email'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $post = $_POST['acao'] ?? '';

    /* ---------------- Atualizar detalhes ---------------- */
    if ($post === 'salvar') {
        $acao = 'editar';
        $form['name']      = trim($_POST['name'] ?? '');
        $form['last_name'] = trim($_POST['last_name'] ?? '');
        $form['username']  = trim($_POST['user'] ?? '');
        $form['email']     = trim($_POST['email'] ?? '');
        $atual    = $_POST['pass_atual'] ?? '';
        $nova     = $_POST['pass'] ?? '';
        $confirma = $_POST['pass2'] ?? '';

        if (mb_strlen($form['name']) < 2 || mb_strlen($form['last_name']) < 2) {
            $erro = 'Informe seu nome e sobrenome.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $form['username'])) {
            $erro = 'O ID do usuário precisa ter de 3 a 50 caracteres (letras, números, ponto, hífen ou _).';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif ($nova !== '' && strlen($nova) < 4) {
            $erro = 'A nova senha precisa ter pelo menos 4 caracteres.';
        } elseif ($nova !== $confirma) {
            $erro = 'A verificação não bate com a nova senha.';
        } elseif ($nova !== '' && !password_verify($atual, $conta['password'])) {
            $erro = 'Para criar uma nova senha, informe corretamente a senha atual.';
        } else {
            $dup = $pdo->prepare('SELECT username, email FROM users WHERE (username = ? OR email = ?) AND id <> ?');
            $dup->execute([$form['username'], $form['email'], $uid]);
            $outro = $dup->fetch();

            if ($outro) {
                $erro = strcasecmp($outro['username'], $form['username']) === 0
                    ? 'Esse ID de usuário já está em uso.'
                    : 'Esse e-mail já está em uso.';
            } else {
                if ($nova !== '') {
                    $upd = $pdo->prepare('UPDATE users SET name = ?, last_name = ?, username = ?, email = ?, password = ? WHERE id = ?');
                    $upd->execute([$form['name'], $form['last_name'], $form['username'], $form['email'], password_hash($nova, PASSWORD_DEFAULT), $uid]);
                } else {
                    $upd = $pdo->prepare('UPDATE users SET name = ?, last_name = ?, username = ?, email = ? WHERE id = ?');
                    $upd->execute([$form['name'], $form['last_name'], $form['username'], $form['email'], $uid]);
                }

                $_SESSION['username'] = $form['username'];
                $_SESSION['name']     = nome_completo(['name' => $form['name'], 'last_name' => $form['last_name'], 'username' => $form['username']]);

                notificar($pdo, 'usuario', 'atualizou os dados da própria conta');
                header('Location: usuarios.php?acao=visualizar&ok=1');
                exit;
            }
        }
    }

    /* ---------------- Excluir a própria conta ---------------- */
    if ($post === 'excluir') {
        $acao = 'excluir';
        if (!password_verify($_POST['pass_excluir'] ?? '', $conta['password'])) {
            $erroExcluir = 'Senha incorreta. A conta não foi excluída.';
        } else {
            $nomeAntigo = nome_completo($conta);

            // As notificações desta pessoa são apagadas junto (ON DELETE CASCADE);
            // as que ela causou nas dos outros continuam, com o nome guardado.
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            notificar($pdo, 'usuario', 'excluiu a própria conta', null, ['id' => null, 'name' => $nomeAntigo]);

            $_SESSION = [];
            session_destroy();
            header('Location: login.php?msg=conta_excluida');
            exit;
        }
    }
}

require __DIR__ . '/includes/layout_top.php';

function e(?string $s): string { return htmlspecialchars((string) $s); }
function data_br(?string $d): string { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }
?>

<div class="view-head">
  <div>
    <div class="view-title">Usuários</div>
    <div class="view-sub">Visualize, edite ou exclua a sua conta.</div>
  </div>
</div>

<div class="security-note" role="note">
  <div class="security-note-ico">🔒</div>
  <div>
    <b>Mensagem de segurança</b>
    Só você vê e altera esta conta. Nunca compartilhe a sua senha com ninguém.
    Para criar uma nova senha ou excluir a conta é preciso confirmar a senha atual, e
    <b>excluir a conta é permanente</b> e não pode ser desfeito.
  </div>
</div>

<?php if ($acao === ''): ?>
  <!-- ============ 1) Escolha ============ -->
  <div class="section-title">O que você quer fazer?</div>
  <div class="action-cards">
    <a class="action-card" href="usuarios.php?acao=visualizar">
      <div class="action-card-ico">👁</div>
      <div class="action-card-title">Visualizar conta</div>
      <div class="action-card-text">Ver os dados da sua conta, sem alterar nada.</div>
    </a>
    <a class="action-card" href="usuarios.php?acao=editar">
      <div class="action-card-ico">✏️</div>
      <div class="action-card-title">Editar conta</div>
      <div class="action-card-text">Alterar nome, e-mail, ID do usuário ou senha.</div>
    </a>
    <a class="action-card danger" href="usuarios.php?acao=excluir">
      <div class="action-card-ico">🗑</div>
      <div class="action-card-title">Excluir conta</div>
      <div class="action-card-text">Remover a sua conta de forma permanente.</div>
    </a>
  </div>

<?php elseif ($acao === 'visualizar'): ?>
  <!-- ============ 2) Visualizar (somente leitura) ============ -->
  <div class="detail-box">
    <div class="detail-legend">Detalhes da Conta</div>
    <?php if ($ok): ?><div class="form-msg ok"><?= e($ok) ?></div><?php endif; ?>

    <div class="detail-row"><div class="dl">ID do Usuário</div><div class="dv"><?= e($conta['username']) ?></div></div>
    <div class="detail-row"><div class="dl">Nome</div><div class="dv"><?= e($conta['name']) ?></div></div>
    <div class="detail-row"><div class="dl">Sobrenome</div><div class="dv"><?= e($conta['last_name']) ?></div></div>
    <div class="detail-row"><div class="dl">E-mail</div><div class="dv"><?= e($conta['email']) ?></div></div>
    <div class="detail-row"><div class="dl">Tipo</div><div class="dv">Usuário</div></div>
    <div class="detail-row"><div class="dl">Criado</div><div class="dv plain"><?= e(data_br($conta['created_at'])) ?></div></div>
    <div class="detail-row"><div class="dl">Modificado</div><div class="dv plain"><?= e(data_br($conta['updated_at'])) ?></div></div>
    <div class="detail-row"><div class="dl">Identificação Interna</div><div class="dv plain mono"><?= e($conta['uuid']) ?></div></div>

    <div class="detail-actions">
      <a class="btn-ghost" href="usuarios.php">Voltar</a>
      <a class="btn" href="usuarios.php?acao=editar">Editar conta</a>
    </div>
  </div>

<?php elseif ($acao === 'editar'): ?>
  <!-- ============ 3) Editar ============ -->
  <form method="post" action="usuarios.php?acao=editar" class="detail-box" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar">
    <div class="detail-legend">Editar Conta</div>

    <?php if ($erro): ?><div class="form-msg erro"><?= e($erro) ?></div><?php endif; ?>
    <div class="detail-req"><span class="req">*</span> Indica um campo obrigatório.</div>

    <div class="detail-row"><label class="dl" for="user">ID do Usuário</label>
      <input class="dv" type="text" id="user" name="user" required value="<?= e($form['username']) ?>"></div>
    <div class="detail-row"><label class="dl" for="name"><span class="req">*</span> Nome</label>
      <input class="dv" type="text" id="name" name="name" required value="<?= e($form['name']) ?>"></div>
    <div class="detail-row"><label class="dl" for="last_name"><span class="req">*</span> Sobrenome</label>
      <input class="dv" type="text" id="last_name" name="last_name" required value="<?= e($form['last_name']) ?>"></div>
    <div class="detail-row"><label class="dl" for="email"><span class="req">*</span> E-mail</label>
      <input class="dv" type="email" id="email" name="email" required value="<?= e($form['email']) ?>"></div>
    <div class="detail-row"><label class="dl" for="pass_atual">Senha Atual</label>
      <input class="dv" type="password" id="pass_atual" name="pass_atual" autocomplete="current-password" placeholder="só para criar uma nova senha"></div>
    <div class="detail-row"><label class="dl" for="pass">Criar uma Nova Senha</label>
      <input class="dv" type="password" id="pass" name="pass" autocomplete="new-password" placeholder="mínimo 4 caracteres"></div>
    <div class="detail-row"><label class="dl" for="pass2">Verificar Nova Senha</label>
      <input class="dv" type="password" id="pass2" name="pass2" autocomplete="new-password"></div>
    <div class="detail-row"><div class="dl">Tipo</div><div class="dv locked">Usuário</div></div>
    <div class="detail-row"><div class="dl">Criado</div><div class="dv plain"><?= e(data_br($conta['created_at'])) ?></div></div>
    <div class="detail-row"><div class="dl">Modificado</div><div class="dv plain"><?= e(data_br($conta['updated_at'])) ?></div></div>
    <div class="detail-row"><div class="dl">Identificação Interna</div><div class="dv plain mono"><?= e($conta['uuid']) ?></div></div>

    <div class="detail-actions">
      <a class="btn-ghost" href="usuarios.php">Cancelar Alterações</a>
      <button type="submit" class="btn">Atualizar Detalhes</button>
    </div>
  </form>

<?php else: ?>
  <!-- ============ 4) Excluir (confirmação) ============ -->
  <form method="post" action="usuarios.php?acao=excluir" class="detail-box danger-box" autocomplete="off"
        onsubmit="return confirm('Excluir sua conta definitivamente? Esta ação não pode ser desfeita.');">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="excluir">
    <div class="detail-legend">Excluir Conta</div>

    <div class="form-msg erro">
      Você está prestes a excluir a sua conta. Isso apaga o seu acesso e as suas notificações
      de forma <b>permanente</b>. Os dados do sistema (máquinas, alertas e locais) continuam
      disponíveis para os demais usuários.
    </div>
    <?php if ($erroExcluir): ?><div class="form-msg erro"><?= e($erroExcluir) ?></div><?php endif; ?>

    <div class="detail-row"><div class="dl">ID do Usuário</div><div class="dv"><?= e($conta['username']) ?></div></div>
    <div class="detail-row"><div class="dl">Nome</div><div class="dv"><?= e(nome_completo($conta)) ?></div></div>
    <div class="detail-row"><div class="dl">E-mail</div><div class="dv"><?= e($conta['email']) ?></div></div>
    <div class="detail-row"><label class="dl" for="pass_excluir"><span class="req">*</span> Sua Senha</label>
      <input class="dv" type="password" id="pass_excluir" name="pass_excluir" required autocomplete="current-password" placeholder="digite para confirmar"></div>

    <div class="detail-actions">
      <a class="btn-ghost" href="usuarios.php">Cancelar</a>
      <button type="submit" class="btn-danger inline">Confirmar exclusão da conta</button>
    </div>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
