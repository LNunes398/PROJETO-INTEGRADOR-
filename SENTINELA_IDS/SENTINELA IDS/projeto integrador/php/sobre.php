<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
exigir_login();

$pagina_atual  = 'sobre';
$titulo_pagina = 'Sobre';

require __DIR__ . '/includes/layout_top.php';
?>

<div class="view-head">
  <div>
    <div class="view-title">Sobre</div>
    <div class="view-sub">Informações do projeto.</div>
  </div>
</div>

<div class="about-box">
  <h3>Sentinela — Sistema de Detecção de Intrusão (IDS)</h3>
  <p>
    Projeto integrador do curso Técnico em Informática, disciplina Programação
    para Internet — IFRN Campus Ceará-Mirim. O painel monitora máquinas de
    diferentes setores e registra alertas de possíveis intrusões (varreduras
    de porta, tentativas de força bruta, etc.).
  </p>
  <ul class="about-list">
    <li><b>Frontend</b> HTML, CSS e PHP (páginas server-side)</li>
    <li><b>Banco de dados</b> MySQL (ver <code>schema.sql</code>)</li>
    <li><b>Autenticação</b> sessão PHP + senha com hash (bcrypt)</li>
  </ul>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
