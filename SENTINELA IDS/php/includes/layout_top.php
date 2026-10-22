<?php
/**
 * Barra de navegação (topo) + abertura da página, incluído no topo
 * das páginas internas. Espera $pagina_atual definida antes do include
 * ('painel' | 'maquinas' | 'alertas' | 'locais' | 'notificacoes' | 'usuarios' | 'sobre').
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/notificacoes.php';

$user       = usuario_atual();
$naoLidas   = contar_nao_lidas($pdo, $user['id']);
$stmtUltimo = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?');
$stmtUltimo->execute([$user['id']]);
$ultimoNotifId = (int) $stmtUltimo->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sentinela · <?= htmlspecialchars($titulo_pagina ?? 'Painel IDS') ?></title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
  <div class="navbar-inner">
    <div class="brand">Sentinela<span>IDS</span></div>
    <nav class="navbar-nav">
      <a href="dashboard.php" class="nav-link <?= $pagina_atual === 'painel' ? 'active' : '' ?>">Painel</a>
      <a href="maquinas.php" class="nav-link <?= $pagina_atual === 'maquinas' ? 'active' : '' ?>">Máquinas</a>
      <a href="alertas.php" class="nav-link <?= $pagina_atual === 'alertas' ? 'active' : '' ?>">Alertas</a>
      <a href="locais.php" class="nav-link <?= $pagina_atual === 'locais' ? 'active' : '' ?>">Locais</a>
      <a href="notificacoes.php" class="nav-link <?= $pagina_atual === 'notificacoes' ? 'active' : '' ?>">Notificações<span class="nav-badge notif-badge" id="notif-badge"<?= $naoLidas ? '' : ' hidden' ?>><?= $naoLidas > 99 ? '99+' : $naoLidas ?></span></a>
      <a href="usuarios.php" class="nav-link <?= $pagina_atual === 'usuarios' ? 'active' : '' ?>">Usuários</a>
      <a href="sobre.php" class="nav-link <?= $pagina_atual === 'sobre' ? 'active' : '' ?>">Sobre</a>
    </nav>
    <div class="navbar-right">
      <span class="status-pill">👤 <?= htmlspecialchars($user['name']) ?></span>
      <a href="logout.php" class="logout-btn" onclick="return confirm('Sair do painel?');">Sair</a>
    </div>
  </div>
</header>

<div id="toast-wrap"></div>

<main class="page">
