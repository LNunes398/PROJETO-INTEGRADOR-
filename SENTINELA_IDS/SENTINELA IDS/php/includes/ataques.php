<?php
/**
 * Base de conhecimento dos ataques detectados: o que é, comportamento normal
 * x comportamento do ataque, e o texto do "que foi encontrado" (com {origem},
 * {destino}, {porta}, {pacotes} preenchidos por alerta).
 */
function ataques_info(): array
{
    static $info = null;
    if ($info === null) {
        $info = json_decode(<<<'JSON'
{
 "syn_flood": {
  "nome": "SYN Flood",
  "resumo": "Todo acesso TCP (site, SSH, compartilhamento de arquivos) começa com um \"aperto de mãos\" de três passos. No SYN Flood o atacante só faz o primeiro passo, repetidas vezes e muito rápido, sem nunca concluir. O computador atacado reserva memória para cada tentativa e, quando a fila enche, deixa de atender pessoas de verdade.",
  "normal": {
   "titulo": "Comportamento normal",
   "pacotes": [
    "SYN",
    "SYN-ACK",
    "ACK"
   ],
   "texto": "O cliente pede a conexão (SYN), o servidor responde que aceita (SYN-ACK) e o cliente confirma (ACK). Só então os dados trafegam. Cada conexão completa gera exatamente estes três pacotes."
  },
  "malicioso": {
   "titulo": "Comportamento do ataque",
   "pacotes": [
    "SYN",
    "SYN",
    "SYN",
    "SYN",
    "SYN",
    "..."
   ],
   "texto": "O atacante envia SYN atrás de SYN, muitas vezes com endereços falsos, e nunca devolve o ACK. O servidor responde cada um com SYN-ACK e fica esperando, com a conexão pela metade, até esgotar seus recursos."
  },
  "achado": "O Sentinela viu o IP {origem} enviar {pacotes} pacotes SYN para {destino} (porta {porta}) sem completar as conexões. Um usuário comum envia poucos SYN e conclui o ACK; aqui o volume e a falta de conclusão fogem desse padrão.",
  "acao": "Verifique se o IP de origem é conhecido, bloqueie-o no firewall se não for, e acompanhe se o serviço atacado ficou lento ou indisponível."
 },
 "port_scan": {
  "nome": "Varredura de portas (Port Scan)",
  "resumo": "Cada serviço de um computador atende em uma \"porta\" (22 = SSH, 80 = web, 445 = arquivos). Na varredura, o atacante testa muitas portas, uma atrás da outra, para descobrir quais estão abertas e quais serviços podem ser explorados. Costuma ser a fase de reconhecimento antes de um ataque maior.",
  "normal": {
   "titulo": "Comportamento normal",
   "pacotes": [
    "SYN → porta 443",
    "SYN-ACK",
    "ACK"
   ],
   "texto": "Um usuário legítimo fala com uma ou poucas portas conhecidas e conclui a conexão."
  },
  "malicioso": {
   "titulo": "Comportamento do ataque",
   "pacotes": [
    "SYN → 21",
    "SYN → 22",
    "SYN → 23",
    "SYN → 80",
    "SYN → 445",
    "..."
   ],
   "texto": "Uma mesma origem manda SYN para dezenas ou centenas de portas diferentes em pouco tempo, e na maioria das vezes não conclui a conexão."
  },
  "achado": "O Sentinela viu o IP {origem} sondar {destino} com {pacotes} pacotes SYN em sequência (última porta observada: {porta}). Isso é típico de quem está mapeando quais serviços estão abertos.",
  "acao": "Confirme se o IP de origem é uma ferramenta autorizada (ex.: o próprio laboratório). Se não for, bloqueie-o e feche as portas que não precisam estar abertas."
 },
 "half_open": {
  "nome": "Conexão TCP incompleta (half-open)",
  "resumo": "É quando uma origem inicia conexões e as abandona no meio do caminho. Acontece em varreduras discretas (\"stealth scan\") e em ataques de negação de serviço em pequena escala. Uma ou outra conexão incompleta é normal, mas muitas vindas do mesmo lugar são suspeitas.",
  "normal": {
   "titulo": "Comportamento normal",
   "pacotes": [
    "SYN",
    "SYN-ACK",
    "ACK"
   ],
   "texto": "Toda conexão iniciada é concluída com o ACK final."
  },
  "malicioso": {
   "titulo": "Comportamento do ataque",
   "pacotes": [
    "SYN",
    "SYN-ACK",
    "(sem ACK)",
    "SYN",
    "SYN-ACK",
    "(sem ACK)"
   ],
   "texto": "A origem inicia a conexão, recebe a resposta do servidor e desaparece sem confirmar, repetindo isso várias vezes."
  },
  "achado": "O Sentinela viu o IP {origem} iniciar {pacotes} conexões com {destino} (porta {porta}) e abandoná-las sem enviar o ACK final.",
  "acao": "Observe se o padrão se repete. Se sim, bloqueie a origem e verifique se o serviço na porta {porta} precisa mesmo estar exposto."
 },
 "brute_force": {
  "nome": "Força bruta",
  "resumo": "O atacante tenta adivinhar usuário e senha de um serviço (como o SSH) testando muitas combinações até acertar. Cada tentativa é uma conexão real, por isso o padrão aparece como excesso de conexões à mesma porta.",
  "normal": {
   "titulo": "Comportamento normal",
   "pacotes": [
    "conexão",
    "login correto",
    "sessão"
   ],
   "texto": "A pessoa se conecta, acerta a senha em uma ou duas tentativas e usa o serviço."
  },
  "malicioso": {
   "titulo": "Comportamento do ataque",
   "pacotes": [
    "conexão",
    "login falho",
    "conexão",
    "login falho",
    "conexão",
    "..."
   ],
   "texto": "A mesma origem abre conexão atrás de conexão na mesma porta, falhando o login a cada vez, em ritmo que uma pessoa não consegue acompanhar."
  },
  "achado": "O Sentinela viu o IP {origem} fazer {pacotes} tentativas de conexão em {destino} (porta {porta}) em curto intervalo, padrão compatível com tentativa de adivinhar senha.",
  "acao": "Bloqueie a origem, troque senhas do serviço atacado se houver suspeita de acesso e considere limitar tentativas ou usar chave SSH."
 }
}
JSON
, true);
    }
    return $info;
}

/** Descobre qual ataque do guia corresponde ao texto do tipo do alerta. */
function ataque_chave(string $tipo): ?string
{
    $t = mb_strtolower($tipo);
    if (str_contains($t, 'flood')) return 'syn_flood';
    if (str_contains($t, 'varredura') || str_contains($t, 'scan')) return 'port_scan';
    if (str_contains($t, 'incompleta') || str_contains($t, 'half')) return 'half_open';
    if (str_contains($t, 'força bruta') || str_contains($t, 'forca bruta') || str_contains($t, 'brute')) return 'brute_force';
    return null;
}

/** Nome amigável do ataque (ou o próprio tipo, se não houver no guia). */
function ataque_nome(string $tipo): string
{
    $k = ataque_chave($tipo);
    return $k ? ataques_info()[$k]['nome'] : $tipo;
}

/** Bloco HTML com a explicação; $alerta preenche o "o que foi encontrado". */
function ataque_explicacao_html(array $inf, ?array $alerta = null): string
{
    $h = fn($v) => htmlspecialchars((string) $v);
    $sub = function (string $txt) use ($alerta) {
        return strtr($txt, [
            '{origem}'  => $alerta['source_ip'] ?? '—',
            '{destino}' => $alerta['machine_name'] ?? '—',
            '{porta}'   => $alerta['port'] ?? ($alerta ? '—' : 'afetada'),
            '{pacotes}' => $alerta['syn_count'] ?? '—',
        ]);
    };
    $seq = function (array $pacotes, string $cls) use ($h) {
        return '<div class="atk-seq">' . implode('<span class="atk-arrow">›</span>',
            array_map(fn($p) => '<span class="atk-pkt ' . $cls . '">' . $h($p) . '</span>', $pacotes)) . '</div>';
    };
    $out  = '<div class="atk-sec"><h4>O que é</h4><p>' . $h($inf['resumo']) . '</p></div>';
    $out .= '<div class="atk-sec"><h4>' . $h($inf['normal']['titulo']) . '</h4>' . $seq($inf['normal']['pacotes'], 'ok') . '<p>' . $h($inf['normal']['texto']) . '</p></div>';
    $out .= '<div class="atk-sec"><h4>' . $h($inf['malicioso']['titulo']) . '</h4>' . $seq($inf['malicioso']['pacotes'], 'bad') . '<p>' . $h($inf['malicioso']['texto']) . '</p></div>';
    if ($alerta) {
        $out .= '<div class="atk-sec"><h4>O que o Sentinela encontrou neste caso</h4><div class="atk-found"><p>' . $h($sub($inf['achado'])) . '</p></div></div>';
    }
    $out .= '<div class="atk-sec"><h4>O que fazer</h4><p>' . $h($sub($inf['acao'])) . '</p></div>';
    return $out;
}
