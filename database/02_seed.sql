-- =========================================================
-- Monitor - Datos iniciales
-- =========================================================
USE monitor_db;

-- Usuario administrador: admin@monitor.com / admin123
-- (Ya no se siembran equipos de prueba: cada persona crea su cuenta y
-- registra sus propios equipos desde la pantalla "Equipos". Si quieres
-- los 3 equipos simulados de antes, ver demo_equipos.sql.)
INSERT INTO usuarios (nombre, contrasena, email) VALUES
  ('Administrador', '$2b$10$rZg801BoJUI3bOeynhAUZuPVZpPMGxsP33RAYf0LnMWUQkrb0m2Jy', 'admin@monitor.com');
