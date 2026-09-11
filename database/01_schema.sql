-- =========================================================
-- Monitor - Esquema de Base de Datos (según diagrama E-R del equipo)
-- =========================================================
CREATE DATABASE IF NOT EXISTS monitor_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE monitor_db;

CREATE TABLE usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  -- Nota: se usa "contrasena" (sin ñ) en vez de "contraseña" del diagrama
  -- original, por seguridad de compatibilidad de identificadores entre
  -- distintas herramientas/SO. El significado es el mismo.
  contrasena VARCHAR(255) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE hosts (
  id_host INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  nombre_host VARCHAR(100) NOT NULL,
  ip_direccion VARCHAR(100) NOT NULL,
  ip_mac VARCHAR(100) NOT NULL UNIQUE,
  CONSTRAINT fk_hosts_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lecturas (
  id_lectura INT AUTO_INCREMENT PRIMARY KEY,
  id_host INT NOT NULL,
  cpu DECIMAL(5,2) NOT NULL,
  ram DECIMAL(5,2) NOT NULL,
  disco DECIMAL(5,2) NOT NULL,
  fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lecturas_host FOREIGN KEY (id_host)
    REFERENCES hosts(id_host) ON DELETE CASCADE,
  INDEX idx_lecturas_host_fecha (id_host, fecha_hora)
) ENGINE=InnoDB;

CREATE TABLE alertas_umbrales (
  id_alerta INT AUTO_INCREMENT PRIMARY KEY,
  id_host INT NOT NULL,
  metrica ENUM('cpu','ram','disco') NOT NULL,
  valor_limite DECIMAL(5,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_alertas_host FOREIGN KEY (id_host)
    REFERENCES hosts(id_host) ON DELETE CASCADE
) ENGINE=InnoDB;
