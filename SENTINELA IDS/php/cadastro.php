<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/notificacoes.php';

if (usuario_logado()) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['name'] ?? '');
    $sobrenome = trim($_POST['last_name'] ?? '');
    $username  = trim($_POST['user'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $senha     = $_POST['pass'] ?? '';
    $confirmar = $_POST['pass2'] ?? '';

    if ($nome === '' || $sobrenome === '' || $username === '' || $email === '' || $senha === '' || $confirmar === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (mb_strlen($nome) < 2 || mb_strlen($sobrenome) < 2) {
        $erro = 'Informe seu nome e sobrenome.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $erro = 'O usuário precisa ter de 3 a 50 caracteres (letras, números, ponto, hífen ou _).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 4) {
        $erro = 'A senha precisa ter pelo menos 4 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        $existente = $stmt->fetch();

        if ($existente) {
            $erro = strcasecmp($existente['username'], $username) === 0
                ? 'Esse usuário já existe.'
                : 'Esse e-mail já está cadastrado.';
        } else {
            try {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $ins  = $pdo->prepare('INSERT INTO users (uuid, name, last_name, username, email, password) VALUES (?, ?, ?, ?, ?, ?)');
                $ins->execute([gerar_uuid(), $nome, $sobrenome, $username, $email, $hash]);
                $novoId = (int) $pdo->lastInsertId();

                // Avisa quem já usa o sistema (o novo usuário não recebe a própria).
                notificar($pdo, 'usuario', 'criou uma conta no Sentinela', null,
                          ['id' => $novoId, 'name' => $nome . ' ' . $sobrenome], $novoId);

                // Depois do cadastro a pessoa já entra direto no sistema.
                iniciar_sessao(['id' => $novoId, 'name' => $nome, 'last_name' => $sobrenome, 'username' => $username]);
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $e) {
                $erro = 'Não foi possível criar a conta: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sentinela · Criar conta</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="login-screen">
  <div class="login-card">
    <div class="login-title">Sentinela<span>IDS</span></div>
    <div class="login-sub">Crie a sua conta pessoal para acessar o painel de monitoramento.</div>

    <?php if ($erro): ?>
      <div class="form-msg erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="field">
        <label for="name">Nome</label>
        <input type="text" id="name" name="name" autocomplete="given-name" placeholder="seu nome" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="last_name">Sobrenome</label>
        <input type="text" id="last_name" name="last_name" autocomplete="family-name" placeholder="seu sobrenome" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="user">Usuário</label>
        <input type="text" id="user" name="user" autocomplete="username" placeholder="ex: analista01" value="<?= htmlspecialchars($_POST['user'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" autocomplete="email" placeholder="voce@exemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="pass">Senha</label>
        <input type="password" id="pass" name="pass" autocomplete="new-password" placeholder="mínimo 4 caracteres">
      </div>
      <div class="field">
        <label for="pass2">Confirmar senha</label>
        <input type="password" id="pass2" name="pass2" autocomplete="new-password" placeholder="repita a senha">
      </div>
      <button type="submit" class="login-btn">Criar conta</button>
    </form>

    <div class="login-hint">
      Já tem conta? <a href="login.php">Fazer login</a>
    </div>
  </div>
</div>

</body>
</html>
