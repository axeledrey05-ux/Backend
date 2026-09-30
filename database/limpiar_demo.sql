-- =========================================================
-- Borra los 3 equipos de prueba (equipo-1/2/3) que se sembraron al
-- inicio, junto con sus lecturas y alertas (ON DELETE CASCADE).
-- Solo borra los que sigan a nombre del administrador (id_usuario = 1)
-- y con las MAC simuladas, así que no toca equipos reales.
--
-- Para una base de datos que YA existe (el seed solo corre en una BD vacía):
--   docker compose exec -T db mysql -uroot -proot_pass monitor_db < database/limpiar_demo.sql
-- =========================================================
USE monitor_db;

DELETE FROM hosts
WHERE id_usuario = 1
  AND ip_mac IN ('02:42:ac:14:00:0b', '02:42:ac:14:00:0c', '02:42:ac:14:00:0d');
