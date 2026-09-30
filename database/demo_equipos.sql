-- =========================================================
-- OPCIONAL: 3 equipos simulados (equipo-1/2/3) para demostraciones.
-- NO se carga automáticamente. Van con los colectores de Docker del
-- perfil "demo" (ver docker-compose.yml). Ejecutar solo si se quieren:
--   docker compose exec -T db mysql -uroot -proot_pass monitor_db < database/demo_equipos.sql
-- =========================================================
USE monitor_db;

INSERT INTO hosts (id_usuario, nombre_host, ip_direccion, ip_mac) VALUES
  (1, 'equipo-1', '172.28.0.11', '02:42:ac:14:00:0b'),
  (1, 'equipo-2', '172.28.0.12', '02:42:ac:14:00:0c'),
  (1, 'equipo-3', '172.28.0.13', '02:42:ac:14:00:0d');
