<?php
/**
 * Ações sobre as notificações do usuário logado (sempre via POST + CSRF):
 *   ler         -> marca UMA como lida
 *   ler_todas   -> marca todas como lidas
 *   apagar      -> apaga UMA, mas só se já estiver lida
 *   apagar_lidas-> apaga todas as lidas
 * Uma notificação NÃO lida nunca é apagada: primeiro precisa ser lida.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
exigir_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: notificacoes.php');
    exit;
}
csrf_validar();

$uid  = usuario_atual()['id'];
$acao = $_POST['acao'] ?? '';
$id   = (int) ($_POST['id'] ?? 0);

switch ($acao) {
    case 'ler':
        $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ? AND is_read = 0')
            ->execute([$id, $uid]);
        break;
    case 'ler_todas':
        $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0')
            ->execute([$uid]);
        break;
    case 'apagar':
        $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ? AND is_read = 1')
            ->execute([$id, $uid]);
        break;
    case 'apagar_lidas':
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1')
            ->execute([$uid]);
        break;
}

$f = $_POST['f'] ?? 'nao_lidas';
if (!in_array($f, ['nao_lidas', 'lidas', 'todas'], true)) {
    $f = 'nao_lidas';
}
header('Location: notificacoes.php?f=' . $f);
exit;
