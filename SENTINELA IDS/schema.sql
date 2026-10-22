-- =========================================================
-- Sentinela IDS — Esquema do banco de dados
-- Compatível com MySQL / MySQL Workbench
--
-- Como usar no Workbench:
--   1. Abra o MySQL Workbench e conecte na sua instância.
--   2. File > Open SQL Script... > selecione este arquivo.
--   3. Execute o script inteiro (ícone de raio, ou Ctrl+Shift+Enter).
--   4. O schema "sentinela_ids" será criado com as tabelas e
--      alguns dados iniciais (locais e máquinas de exemplo). Não há
--      usuário pronto: crie o seu em php/cadastro.php.
-- =========================================================

CREATE DATABASE IF NOT EXISTS sentinela_ids
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sentinela_ids;

-- Garante que os textos acentuados (ç, ã, é...) sejam lidos/gravados
-- corretamente, independente do charset padrão do cliente MySQL usado
-- para rodar este script (evita "forÃ§a" no lugar de "força").
SET NAMES utf8mb4;

-- ---------------------------------------------------------
-- Tabela: locations
-- Guarda os setores/locais onde as máquinas ficam
-- (Lab de Redes, TI, Secretaria, etc.)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS locations (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80) NOT NULL UNIQUE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO locations (name) VALUES
  ('Lab de Redes'),
  ('TI'),
  ('Secretaria'),
  ('Diretoria'),
  ('Sala dos Professores'),
  ('Biblioteca'),
  ('Outro')
ON DUPLICATE KEY UPDATE name = name;

-- ---------------------------------------------------------
-- Tabela: users
-- Cada pessoa tem a sua própria conta (nome, usuário, e-mail e
-- senha). Não existe mais usuário "root" de fábrica: a primeira
-- conta é criada pela tela de cadastro (php/cadastro.php).
-- A senha é guardada como HASH (password_hash do PHP), nunca em
-- texto puro.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  uuid          CHAR(36) UNIQUE,              -- "Identificação interna" mostrada em Usuários
  name          VARCHAR(100),                 -- nome
  last_name     VARCHAR(100),                 -- sobrenome
  username      VARCHAR(50)  NOT NULL UNIQUE, -- "ID do usuário" (login)
  email         VARCHAR(120) UNIQUE,
  password      VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabela: machines
-- Máquinas ativadas para monitoramento, cada uma vinculada
-- a um local (location_id)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS machines (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  ip            VARCHAR(45)  NOT NULL,
  mac           VARCHAR(17),
  so            VARCHAR(100),
  location_id   INT,
  status        ENUM('online', 'alerta', 'offline') NOT NULL DEFAULT 'online',
  last_seen     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_machines_location
    FOREIGN KEY (location_id) REFERENCES locations(id)
    ON DELETE SET NULL,
  UNIQUE KEY uq_machines_ip (ip)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabela: alerts
-- Eventos/detecções de cada máquina monitorada
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS alerts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  machine_id    INT NOT NULL,
  source_ip     VARCHAR(45),
  source_mac    VARCHAR(17),
  source_so     VARCHAR(100),
  dest_ip       VARCHAR(45),
  type          VARCHAR(150) NOT NULL,
  port          VARCHAR(50),
  syn_count     INT DEFAULT 0,
  status        ENUM('aberto', 'resolvido') NOT NULL DEFAULT 'aberto',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_alerts_machine
    FOREIGN KEY (machine_id) REFERENCES machines(id)
    ON DELETE CASCADE,
  INDEX idx_alerts_machine (machine_id),
  INDEX idx_alerts_created_at (created_at),
  INDEX idx_alerts_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Dados de exemplo, só para o painel PHP já nascer com
-- conteúdo pra mostrar (pode apagar pelas telas do sistema).
-- ---------------------------------------------------------
INSERT INTO machines (name, ip, mac, so, location_id, status) VALUES
  ('PC-LABREDES-01', '192.168.56.10', '08:00:27:1A:2B:3C', 'Ubuntu 22.04', 1, 'alerta'),
  ('PC-TI-02',       '192.168.56.20', '08:00:27:4D:5E:6F', 'Windows 10',   2, 'online')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO alerts (machine_id, source_ip, source_mac, source_so, dest_ip, type, port, syn_count, status) VALUES
  (1, '192.168.56.10', '08:00:27:1A:2B:3C', 'Ubuntu 22.04', '192.168.56.1', 'Port scan (SYN flood)', '22', 143, 'aberto'),
  (2, '192.168.56.20', '08:00:27:4D:5E:6F', 'Windows 10',   '192.168.56.1', 'Tentativa de força bruta SSH', '22', 12, 'resolvido');

-- ---------------------------------------------------------
-- Tabela: notifications
-- Toda alteração feita no sistema (máquina ativada/editada/removida,
-- alerta resolvido, local alterado, conta criada/removida...) gera
-- UMA notificação PARA CADA usuário. Assim cada pessoa controla as
-- suas: a notificação fica guardada no banco até ser aberta, marcada
-- como lida (is_read = 1) e só então apagada de vez.
--   actor_id   -> quem fez a alteração (NULL se for o sistema ou se a
--                 conta dessa pessoa foi removida depois)
--   actor_name -> nome de quem fez, guardado na hora, para continuar
--                 aparecendo mesmo que a conta seja removida
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  actor_id      INT NULL,
  actor_name    VARCHAR(100) NULL,
  type          VARCHAR(20)  NOT NULL DEFAULT 'sistema',
  message       VARCHAR(255) NOT NULL,
  link          VARCHAR(120) NULL,
  is_read       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at       TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_notif_user
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_actor
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_notif_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Gatilho: todo alerta novo inserido na tabela "alerts" (pelo
-- sensor/agente IDS, direto no banco) notifica todos os usuários.
-- Se importar por um cliente que não entende DELIMITER, rode este
-- bloco separadamente no MySQL Workbench.
-- ---------------------------------------------------------
DROP TRIGGER IF EXISTS trg_alerts_notificar;
DELIMITER $$
CREATE TRIGGER trg_alerts_notificar
AFTER INSERT ON alerts
FOR EACH ROW
BEGIN
  INSERT INTO notifications (user_id, type, message, link)
  SELECT u.id, 'alerta',
         CONCAT('Novo alerta em ',
                COALESCE((SELECT m.name FROM machines m WHERE m.id = NEW.machine_id), 'máquina removida'),
                ': ', NEW.type),
         'alertas.php'
  FROM users u;
END$$
DELIMITER ;

-- ---------------------------------------------------------
-- MIGRAÇÃO (só para quem já tinha o banco da versão anterior).
-- Rode manualmente, uma vez:
--   ALTER TABLE users ADD COLUMN uuid       CHAR(36)     NULL UNIQUE AFTER id;
--   ALTER TABLE users ADD COLUMN name       VARCHAR(100) NULL AFTER uuid;
--   ALTER TABLE users ADD COLUMN last_name  VARCHAR(100) NULL AFTER name;
--   ALTER TABLE users ADD COLUMN email      VARCHAR(120) NULL UNIQUE AFTER username;
--   ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
--   DELETE FROM users WHERE username = 'root';   -- o root/1234 deixou de existir
-- e depois crie a tabela "notifications" e o gatilho acima.
-- ---------------------------------------------------------

-- ---------------------------------------------------------
-- Se você já tinha importado uma versão anterior deste schema
-- (sem a coluna "status" em alerts), rode manualmente:
--   ALTER TABLE alerts ADD COLUMN status ENUM('aberto','resolvido')
--     NOT NULL DEFAULT 'aberto' AFTER syn_count;
-- ---------------------------------------------------------

-- ---------------------------------------------------------
-- Consultas de exemplo (úteis para o painel Web)
-- ---------------------------------------------------------

-- Máquinas com o nome do local já resolvido
-- SELECT m.*, l.name AS location_name
-- FROM machines m
-- LEFT JOIN locations l ON m.location_id = l.id
-- ORDER BY m.id DESC;

-- Alertas mais recentes com o nome da máquina e do local
-- SELECT a.*, m.name AS machine_name, l.name AS location_name
-- FROM alerts a
-- JOIN machines m ON a.machine_id = m.id
-- LEFT JOIN locations l ON m.location_id = l.id
-- ORDER BY a.created_at DESC
-- LIMIT 50;

-- Quantidade de alertas por local
-- SELECT l.name AS location_name, COUNT(a.id) AS total_alertas
-- FROM alerts a
-- JOIN machines m ON a.machine_id = m.id
-- LEFT JOIN locations l ON m.location_id = l.id
-- GROUP BY l.name
-- ORDER BY total_alertas DESC;
