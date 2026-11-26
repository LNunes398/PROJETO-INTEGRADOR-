<?php
require_once __DIR__ . '/includes/auth.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$res = ['ip' => '', 'hostname' => '', 'mac' => '', 'so' => '', 'aviso' => ''];

if     (preg_match('/Windows NT 10/', $ua)) $res['so'] = 'Windows 10/11';
elseif (str_contains($ua, 'Windows'))       $res['so'] = 'Windows';
elseif (str_contains($ua, 'Android'))       $res['so'] = 'Android';
elseif (preg_match('/iPhone|iPad/', $ua))   $res['so'] = 'iOS';
elseif (str_contains($ua, 'Mac OS X'))      $res['so'] = 'macOS';
elseif (str_contains($ua, 'Linux'))         $res['so'] = 'Linux';

if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode($res);
    exit;
}

$res['ip'] = $ip;

if ($ip === '127.0.0.1' || $ip === '::1') {
    $res['hostname'] = gethostname() ?: '';
    $res['ip']       = $_SERVER['SERVER_ADDR'] ?? $ip;
    if ($res['ip'] === '::1') $res['ip'] = '127.0.0.1';
    $res['aviso']    = 'Você está acessando pelo próprio servidor (localhost). Para detectar o MAC, abra o painel de outro computador da rede, pelo IP do servidor.';
    echo json_encode($res);
    exit;
}

$nome = @gethostbyaddr($ip);
if ($nome && $nome !== $ip) $res['hostname'] = $nome;

if (function_exists('exec') && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'arp -a ' . escapeshellarg($ip) : '(ip neigh show ' . escapeshellarg($ip) . ' || arp -n ' . escapeshellarg($ip) . ') 2>/dev/null';
    $saida = [];
    @exec($cmd, $saida);
    if (preg_match('/([0-9a-f]{2}[:-]){5}[0-9a-f]{2}/i', implode("\n", $saida), $m)) {
        $res['mac'] = strtoupper(str_replace('-', ':', $m[0]));
    }
}

echo json_encode($res);
