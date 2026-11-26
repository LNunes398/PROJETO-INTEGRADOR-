<?php
function notificar(PDO $pdo, string $tipo, string $mensagem, ?string $link = null, ?array $ator = null, ?int $excluir_id = null): void
{
    if ($ator === null) {
        $u     = usuario_atual();
        $ator  = $u ? ['id' => $u['id'], 'name' => $u['name']] : ['id' => null, 'name' => 'Sistema'];
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, actor_id, actor_name, type, message, link)
             SELECT id, ?, ?, ?, ?, ? FROM users WHERE id <> ?'
        );
        $stmt->execute([
            $ator['id'],
            mb_substr((string) $ator['name'], 0, 100),
            $tipo,
            mb_substr($mensagem, 0, 255),
            $link,
            $excluir_id ?? 0,
        ]);
    } catch (PDOException $e) {
        error_log('Sentinela: falha ao criar notificação: ' . $e->getMessage());
    }
}

function contar_nao_lidas(PDO $pdo, int $user_id): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

function tempo_atras(string $datahora): string
{
    $ts = strtotime($datahora);
    if (!$ts) {
        return $datahora;
    }
    $d = max(0, time() - $ts);
    if ($d < 60)     return 'agora';
    if ($d < 3600)   return 'há ' . floor($d / 60) . ' min';
    if ($d < 86400)  return 'há ' . floor($d / 3600) . ' h';
    if ($d < 604800) return 'há ' . floor($d / 86400) . ' dia' . (floor($d / 86400) > 1 ? 's' : '');
    return date('d/m/Y H:i', $ts);
}

function texto_notificacao(array $n, int $meu_id): string
{
    if (($n['actor_name'] ?? '') === '' ) {
        return $n['message'];
    }
    $quem = ($n['actor_id'] !== null && (int) $n['actor_id'] === $meu_id) ? 'Você' : $n['actor_name'];
    return $quem . ' ' . $n['message'];
}

function icone_notificacao(string $tipo): string
{
    $icones = [
        'maquina' => '🖥️',
        'alerta'  => '⚠️',
        'local'   => '📍',
        'usuario' => '👤',
    ];
    return $icones[$tipo] ?? '🔔';
}
