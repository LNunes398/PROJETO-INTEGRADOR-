CREATE DATABASE IF NOT EXISTS sentinela_ids
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sentinela_ids;

SET NAMES utf8mb4;

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

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  uuid          CHAR(36) UNIQUE,
  name          VARCHAR(100),
  last_name     VARCHAR(100),
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(120) UNIQUE,
  password      VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS machines (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  ip            VARCHAR(45)  NOT NULL,
  hostname      VARCHAR(255),
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

INSERT INTO machines (name, ip, hostname, mac, so, location_id, status)
SELECT 'PC-LABREDES-01', '192.168.56.10', 'pc-labredes-01', '08:00:27:1A:2B:3C', 'Ubuntu 22.04',
       (SELECT id FROM locations WHERE name = 'Lab de Redes'), 'alerta'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM machines WHERE ip = '192.168.56.10');

INSERT INTO machines (name, ip, hostname, mac, so, location_id, status)
SELECT 'PC-TI-02', '192.168.56.20', 'pc-ti-02', '08:00:27:4D:5E:6F', 'Windows 10',
       (SELECT id FROM locations WHERE name = 'TI'), 'online'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM machines WHERE ip = '192.168.56.20');

INSERT INTO alerts (machine_id, source_ip, source_mac, source_so, dest_ip, type, port, syn_count, status)
SELECT m.id, '192.168.56.10', '08:00:27:1A:2B:3C', 'Ubuntu 22.04', '192.168.56.1', 'SYN Flood', '22', 143, 'aberto'
FROM machines m
WHERE m.ip = '192.168.56.10'
  AND NOT EXISTS (SELECT 1 FROM alerts a WHERE a.machine_id = m.id AND a.source_so = 'Ubuntu 22.04' AND a.type = 'SYN Flood');

INSERT INTO alerts (machine_id, source_ip, source_mac, source_so, dest_ip, type, port, syn_count, status)
SELECT m.id, '192.168.56.20', '08:00:27:4D:5E:6F', 'Windows 10', '192.168.56.1', 'Tentativa de força bruta SSH', '22', 12, 'resolvido'
FROM machines m
WHERE m.ip = '192.168.56.20'
  AND NOT EXISTS (SELECT 1 FROM alerts a WHERE a.machine_id = m.id AND a.source_so = 'Windows 10' AND a.type = 'Tentativa de força bruta SSH');

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
