<?php
/**
 * Conexão com o banco de dados (PDO + MySQL).
 * Ajuste host/usuário/senha conforme o ambiente (XAMPP, WAMP, servidor da escola etc.).
 */

$DB_HOST = 'localhost';
$DB_NAME = 'sentinela_ids';
$DB_USER = 'root';
$DB_PASS = ''; // no XAMPP/WAMP costuma ser vazio; ajuste se o seu MySQL tiver senha

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Erro ao conectar ao banco de dados: ' . htmlspecialchars($e->getMessage()) .
        '<br>Confira se o MySQL está rodando e se o schema.sql foi importado.');
}
