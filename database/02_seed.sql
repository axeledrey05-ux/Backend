-- =========================================================
-- Monitor - Datos iniciales / de prueba
-- =========================================================
USE monitor_db;

-- admin@monitor.com / admin123
INSERT INTO usuarios (nombre, contrasena, email) VALUES
  ('Administrador', '$2b$10$rZg801BoJUI3bOeynhAUZuPVZpPMGxsP33RAYf0LnMWUQkrb0m2Jy', 'admin@monitor.com');

-- Las MAC deben coincidir con "mac_address" de cada colector en docker-compose.yml
INSERT INTO hosts (id_usuario, nombre_host, ip_direccion, ip_mac) VALUES
  (1, 'equipo-1', '172.28.0.11', '02:42:ac:14:00:0b'),
  (1, 'equipo-2', '172.28.0.12', '02:42:ac:14:00:0c'),
  (1, 'equipo-3', '172.28.0.13', '02:42:ac:14:00:0d');

INSERT INTO alertas_umbrales (id_host, metrica, valor_limite) VALUES
  (1, 'cpu', 80.00),
  (1, 'ram', 90.00),
  (2, 'disco', 85.00);
