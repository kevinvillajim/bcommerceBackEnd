-- Script para corregir IDs malformados en la tabla ratings
-- ⚠️ IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Paso 1: Verificar IDs malformados (solo consulta)
SELECT 
    id,
    CAST(SUBSTRING_INDEX(id, '.', 1) AS UNSIGNED) as id_corregido,
    user_id,
    rating,
    type,
    created_at
FROM ratings 
WHERE id LIKE '%.%'
ORDER BY id;

-- Paso 2: Mostrar estadísticas
SELECT 
    COUNT(*) as total_ratings,
    SUM(CASE WHEN id LIKE '%.%' THEN 1 ELSE 0 END) as ids_malformados,
    MAX(CASE WHEN id NOT LIKE '%.%' THEN CAST(id AS UNSIGNED) ELSE 0 END) as max_id_valido
FROM ratings;

-- Paso 3: Crear tabla temporal para respaldo (EJECUTAR PRIMERO)
CREATE TABLE ratings_backup_$(date) AS SELECT * FROM ratings WHERE id LIKE '%.%';

-- Paso 4: Corregir IDs malformados (EJECUTAR CON CUIDADO)
-- NOTA: Ejecutar uno por uno para verificar

-- Para ID 7.132 → 132
UPDATE ratings 
SET id = 132 
WHERE id = '7.132' 
AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM ratings WHERE id = 132) t);

-- Para ID 7.131 → 131  
UPDATE ratings 
SET id = 131 
WHERE id = '7.131'
AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM ratings WHERE id = 131) t);

-- Para ID 7.130 → 130
UPDATE ratings 
SET id = 130 
WHERE id = '7.130'
AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM ratings WHERE id = 130) t);

-- Para ID 7.129 → 129
UPDATE ratings 
SET id = 129 
WHERE id = '7.129'
AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM ratings WHERE id = 129) t);

-- Paso 5: Si hay conflictos, usar IDs siguientes al máximo
-- Obtener el siguiente ID disponible
SET @next_id = (SELECT MAX(CAST(id AS UNSIGNED)) + 1 FROM ratings WHERE id NOT LIKE '%.%');

-- Corregir IDs restantes que tengan conflictos
UPDATE ratings 
SET id = (@next_id := @next_id + 1)
WHERE id LIKE '%.%';

-- Paso 6: Resetear AUTO_INCREMENT
SET @max_id = (SELECT MAX(CAST(id AS UNSIGNED)) FROM ratings);
SET @sql = CONCAT('ALTER TABLE ratings AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Paso 7: Verificar corrección
SELECT 
    COUNT(*) as total_ratings,
    SUM(CASE WHEN id LIKE '%.%' THEN 1 ELSE 0 END) as ids_malformados_restantes,
    MIN(CAST(id AS UNSIGNED)) as min_id,
    MAX(CAST(id AS UNSIGNED)) as max_id
FROM ratings;

-- Paso 8: Mostrar IDs corregidos
SELECT 'IDs corregidos exitosamente' as resultado;