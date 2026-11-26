<?php
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

    if ($post === 'excluir') {
        $acao = 'excluir';
        if (!password_verify($_POST['pass_excluir'] ?? '', $conta['password'])) {
            $erroExcluir = 'Senha incorreta. A conta não foi excluída.';
        } else {
            $nomeAntigo = nome_completo($conta);

            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            notificar($pdo, 'usuario', 'excluiu a própria conta', null, ['id' => null, 'name' => $nomeAntigo]);

            $_SESSION = [];
            session_destroy();
            header('Location: login.php?msg=conta_excluida');
            exit;
        }
    }
}
