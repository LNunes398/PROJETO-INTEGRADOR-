<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';

if (usuario_logado()) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';
$aviso = ($_GET['msg'] ?? '') === 'conta_excluida' ? 'Sua conta foi excluída. Obrigado por usar o Sentinela.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['user'] ?? '');
    $senha = $_POST['pass'] ?? '';

    if ($login === '' || $senha === '') {
        $erro = 'Preencha usuário (ou e-mail) e senha.';
    } else {
        // Entra tanto com o nome de usuário quanto com o e-mail.
        $stmt = $pdo->prepare('SELECT id, name, last_name, username, password FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$login, $login]);
        $linha = $stmt->fetch();

        if ($linha && password_verify($senha, $linha['password'])) {
            iniciar_sessao($linha);
            header('Location: dashboard.php');
            exit;
        }

        $erro = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sentinela · Login</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="login-screen">
  <div class="login-card">
    <div class="login-title">Sentinela<span>IDS</span></div>
    <div class="login-sub">Sistema de Detecção de Intrusão — acesse o painel para monitorar máquinas e alertas.</div>

    <?php if ($aviso): ?>
      <div class="form-msg ok"><?= htmlspecialchars($aviso) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="login-error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="field">
        <label for="user">Usuário ou e-mail</label>
        <input type="text" id="user" name="user" autocomplete="username" placeholder="seu usuário ou e-mail" value="<?= htmlspecialchars($_POST['user'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="pass">Senha</label>
        <input type="password" id="pass" name="pass" autocomplete="current-password" placeholder="sua senha">
      </div>
      <button type="submit" class="login-btn">Entrar</button>
    </form>

    <div class="login-hint">
      Ainda não tem conta? <a href="cadastro.php">Cadastre-se</a>
    </div>
  </div>
</div>

</body>
</html>
