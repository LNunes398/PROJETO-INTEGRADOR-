<?php
$DB_HOST = 'localhost';
$DB_PORT = '3306';
$DB_NAME = 'sentinela_ids';
$DB_USER = 'root';
$DB_PASS = '';

$pdo     = null;
$DB_ERRO = '';

if (!extension_loaded('pdo_mysql')) {
    $DB_ERRO = 'A extensão PHP "pdo_mysql" não está ativa. No XAMPP, abra php.ini e remova o ";" de ";extension=pdo_mysql", depois reinicie o Apache.';
} else {
    try {
        $pdo = new PDO(
            "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        $codigo = (int) ($e->errorInfo[1] ?? 0);
        if ($codigo === 1049) {
            $DB_ERRO = "O banco \"{$DB_NAME}\" não existe. Importe o schema.sql (ou rode o Simulador_Ataques_Rede_CMD.bat, que importa sozinho).";
        } elseif ($codigo === 1045) {
            $DB_ERRO = "Usuário ou senha do MySQL incorretos. Ajuste \$DB_USER e \$DB_PASS em php/config/db.php.";
        } elseif ($codigo === 2002 || $codigo === 2003) {
            $DB_ERRO = "Não foi possível falar com o MySQL em {$DB_HOST}:{$DB_PORT}. Confira se o MySQL está ligado (XAMPP > Start) e se a porta está certa.";
        } else {
            $DB_ERRO = 'Erro ao conectar ao banco de dados: ' . $e->getMessage();
        }
    }
}

if ($pdo === null && !defined('SENTINELA_DIAGNOSTICO')) {
    die('<meta charset="UTF-8"><p><b>Erro de banco de dados.</b></p><p>' . htmlspecialchars($DB_ERRO) .
        '</p><p>Abra <a href="diagnostico.php">diagnostico.php</a> (neste computador) para ver o que está faltando.</p>');
}
