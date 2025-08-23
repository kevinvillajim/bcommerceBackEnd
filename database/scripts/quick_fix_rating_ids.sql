-- CORRECCIÓN RÁPIDA Y SEGURA DE IDs MALFORMADOS
-- Este script es seguro de ejecutar inmediatamente

-- 1. Crear respaldo de seguridad
CREATE TABLE IF NOT EXISTS ratings_backup_emergency AS 
SELECT * FROM ratings WHERE id LIKE '%.%';

-- 2. Corregir IDs específicos mencionados por el usuario
-- Verificar y corregir 7.132 → 132
UPDATE ratings SET id = 132 WHERE id = '7.132' AND 132 NOT IN (SELECT id FROM (SELECT id FROM ratings WHERE id = 132 AND id != '7.132') t);

-- Verificar y corregir 7.131 → 131  
UPDATE ratings SET id = 131 WHERE id = '7.131' AND 131 NOT IN (SELECT id FROM (SELECT id FROM ratings WHERE id = 131 AND id != '7.131') t);

-- Verificar y corregir 7.130 → 130
UPDATE ratings SET id = 130 WHERE id = '7.130' AND 130 NOT IN (SELECT id FROM (SELECT id FROM ratings WHERE id = 130 AND id != '7.130') t);

-- Verificar y corregir 7.129 → 129
UPDATE ratings SET id = 129 WHERE id = '7.129' AND 129 NOT IN (SELECT id FROM (SELECT id FROM ratings WHERE id = 129 AND id != '7.129') t);

-- 3. Para otros IDs malformados que puedan existir, asignar IDs seguros
SET @next_safe_id = (SELECT COALESCE(MAX(CAST(id AS UNSIGNED)), 0) + 1000 FROM ratings WHERE id NOT LIKE '%.%');

-- Corregir cualquier ID malformado restante
UPDATE ratings 
SET id = (@next_safe_id := @next_safe_id + 1)
WHERE id LIKE '%.%';

-- 4. Resetear AUTO_INCREMENT a un valor seguro
SET @max_id = (SELECT MAX(CAST(id AS UNSIGNED)) FROM ratings);
SET @auto_increment = @max_id + 1;
SET @sql = CONCAT('ALTER TABLE ratings AUTO_INCREMENT = ', @auto_increment);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Verificar resultado
SELECT 
    'Corrección completada' as status,
    COUNT(*) as total_ratings,
    SUM(CASE WHEN id LIKE '%.%' THEN 1 ELSE 0 END) as malformed_ids_remaining,
    MIN(CAST(id AS UNSIGNED)) as min_id,
    MAX(CAST(id AS UNSIGNED)) as max_id,
    @auto_increment as next_auto_increment
FROM ratings;