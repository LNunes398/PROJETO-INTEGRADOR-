<?php
/**
 * Controle de sessão/autenticação.
 * Inclua este arquivo no topo de qualquer página que precise saber
 * se o usuário está logado.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- Compatibilidade: PHP sem a extensão mbstring ---------- */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int
    {
        return preg_match_all('/./su', $s) ?: 0;
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $inicio, ?int $tamanho = null): string
    {
        $letras = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_slice($letras, $inicio, $tamanho));
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $s): string
    {
        return strtoupper($s);
    }
}

function usuario_logado(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Bloqueia o acesso a quem não estiver logado, mandando para o login.
 * Chame no topo das páginas internas (painel, máquinas, alertas...).
 */
function exigir_login(): void
{
    if (!usuario_logado()) {
        header('Location: login.php');
        exit;
    }
}

function usuario_atual(): ?array
{
    if (!usuario_logado()) {
        return null;
    }
    return [
        'id'       => (int) $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'name'     => $_SESSION['name'] ?? $_SESSION['username'],
    ];
}

/** Grava na sessão os dados exibidos no topo do painel. */
function iniciar_sessao(array $linha): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int) $linha['id'];
    $_SESSION['username'] = $linha['username'];
    $_SESSION['name']     = nome_completo($linha);
}

/** "Nome Sobrenome" (ou o usuário, se a conta não tiver nome). */
function nome_completo(array $linha): string
{
    $nome = trim(($linha['name'] ?? '') . ' ' . ($linha['last_name'] ?? ''));
    return $nome !== '' ? $nome : $linha['username'];
}

/** UUID v4 usado como "identificação interna" da conta. */
function gerar_uuid(): string
{
    $b    = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/* ---------- CSRF: protege os formulários que alteram dados ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

/** Interrompe a requisição se o token do formulário não bater com o da sessão. */
function csrf_validar(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        die('Requisição inválida (token de segurança). Volte e tente de novo.');
    }
}
