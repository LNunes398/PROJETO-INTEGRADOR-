<?php
// Página de diagnóstico: só abre no próprio computador do servidor (localhost).
define('SENTINELA_DIAGNOSTICO', true);

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Diagnóstico disponível apenas em localhost.');
}

require_once __DIR__ . '/config/db.php';

$itens = [];
$add = function (string $nome, bool $ok, string $detalhe = '') use (&$itens) {
    $itens[] = [$nome, $ok, $detalhe];
};

$add('PHP 7.4 ou mais novo', version_compare(PHP_VERSION, '7.4.0', '>='), 'Versão atual: ' . PHP_VERSION);
$add('Extensão pdo_mysql', extension_loaded('pdo_mysql'));
$add('Conexão com o MySQL / banco "' . $DB_NAME . '"', $pdo !== null, $DB_ERRO);

if ($pdo !== null) {
    $tabelas = ['locations', 'users', 'machines', 'alerts', 'notifications'];
    foreach ($tabelas as $t) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
        $st->execute([$DB_NAME, $t]);
        $add("Tabela $t", (int) $st->fetchColumn() === 1);
    }
    $colunas = [['machines', 'hostname'], ['alerts', 'status'], ['users', 'email']];
    foreach ($colunas as [$t, $c]) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
        $st->execute([$DB_NAME, $t, $c]);
        $add("Coluna $t.$c", (int) $st->fetchColumn() === 1);
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = ? AND trigger_name = ?');
    $st->execute([$DB_NAME, 'trg_alerts_notificar']);
    $add('Gatilho trg_alerts_notificar (notifica alertas novos)', (int) $st->fetchColumn() === 1);

    foreach (['machines' => 'Máquinas cadastradas', 'alerts' => 'Alertas', 'users' => 'Usuários'] as $t => $rot) {
        try {
            $add($rot, true, (string) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn());
        } catch (PDOException $e) {
            $add($rot, false, $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><title>Sentinela · Diagnóstico</title>
<style>body{font-family:system-ui,sans-serif;max-width:760px;margin:30px auto;padding:0 16px}
li{margin:6px 0}.ok{color:#0a7d33}.no{color:#c0392b}small{color:#555}</style></head><body>
<h2>Diagnóstico do Sentinela</h2>
<ul>
<?php foreach ($itens as [$nome, $ok, $det]): ?>
  <li class="<?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✔' : '✘' ?> <b><?= htmlspecialchars($nome) ?></b>
    <?php if ($det !== ''): ?><br><small><?= htmlspecialchars($det) ?></small><?php endif; ?></li>
<?php endforeach; ?>
</ul>
<p><small>Se algo estiver com ✘ no banco, importe <code>schema.sql</code> ou rode <code>Simulador_Ataques_Rede_CMD.bat</code>, que corrige sozinho.</small></p>
</body></html>
