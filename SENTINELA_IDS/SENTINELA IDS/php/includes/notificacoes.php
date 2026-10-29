<?php
/**
 * Notificações compartilhadas via banco de dados.
 *
 * Toda alteração relevante chama notificar(): é criada UMA linha em
 * "notifications" para CADA usuário cadastrado. Cada pessoa lê e apaga
 * as suas de forma independente — a notificação só some de vez depois
 * de ser marcada como lida e apagada (ver notificacao_acao.php).
 */

/**
 * @param string      $tipo        'maquina' | 'alerta' | 'local' | 'usuario' | 'sistema'
 * @param string      $mensagem    texto sem o nome de quem fez ("ativou a máquina \"X\"")
 * @param string|null $link        página relacionada (ex.: 'maquinas.php')
 * @param array|null  $ator        ['id'=>int|null,'name'=>string]; padrão = usuário logado
 * @param int|null    $excluir_id  usuário que NÃO deve receber (ex.: o recém-criado)
 */
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
        // Uma falha ao notificar nunca deve derrubar a ação principal.
        error_log('Sentinela: falha ao criar notificação: ' . $e->getMessage());
    }
}

function contar_nao_lidas(PDO $pdo, int $user_id): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

/** "agora", "há 5 min", "há 2 h", "há 3 dias" ou a data. */
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

/** Texto final mostrado ao usuário: "Maria ativou a máquina X" / "Você ativou..." (texto puro, sem HTML). */
function texto_notificacao(array $n, int $meu_id): string
{
    if (($n['actor_name'] ?? '') === '' ) {
        return $n['message'];
    }
    $quem = ($n['actor_id'] !== null && (int) $n['actor_id'] === $meu_id) ? 'Você' : $n['actor_name'];
    return $quem . ' ' . $n['message'];
}

/** Ícone por tipo de notificação. */
function icone_notificacao(string $tipo): string
{
    return match ($tipo) {
        'maquina' => '🖥️',
        'alerta'  => '⚠️',
        'local'   => '📍',
        'usuario' => '👤',
        default   => '🔔',
    };
}
