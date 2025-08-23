# 🔄 Script de Migración SQLite → MySQL

## Descripción
Este script migra automáticamente todos los datos de una base de datos SQLite a MySQL, manteniendo la integridad referencial y generando reportes detallados.

## 📁 Ubicación
```
database/scripts/migrate_sqlite_to_mysql.php
```

## ⚡ Uso Rápido

### Comando Básico
```bash
php database/scripts/migrate_sqlite_to_mysql.php
```

### Desde Directorio Raíz
```bash
cd "ruta/al/proyecto"
php database/scripts/migrate_sqlite_to_mysql.php
```

## 🔧 Configuración

### 1. Prerequisitos
- ✅ Laravel instalado con Eloquent
- ✅ Base de datos SQLite existente en `database/database.sqlite`  
- ✅ Base de datos MySQL configurada y accesible
- ✅ Migraciones de Laravel ejecutadas en MySQL (`php artisan migrate`)

### 2. Configuración .env
Asegúrate de tener configurado MySQL en tu `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tu_base_datos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

### 3. Modificar Configuración del Script
Si necesitas cambiar rutas o conexiones, edita estas líneas en el script:

```php
// Configuración SQLite (línea ~30)
'database' => __DIR__ . '/../database.sqlite',

// Configuración MySQL (línea ~35-42)
'host' => '127.0.0.1',
'port' => '3306', 
'database' => 'comersia',
'username' => 'root',
'password' => 'test123',
```

## 🚀 Funcionalidades

### ✅ Características Principales
- **Migración Automática**: Detecta y migra todas las tablas automáticamente
- **Orden de Dependencias**: Respeta foreign keys migrando en orden correcto
- **Migración por Lotes**: Procesa datos en lotes de 500 filas para optimizar memoria
- **Manejo de Errores**: Continúa con otras tablas si una falla
- **Reportes Detallados**: Genera logs completos y reportes en Markdown
- **Verificación de Conexiones**: Valida ambas bases de datos antes de migrar
- **Limpieza Automática**: Trunca tablas MySQL antes de insertar datos

### 📊 Orden de Migración
El script migra las tablas en este orden para respetar foreign keys:

1. **Tablas Base** (sin dependencias)
   - `users`
   - `categories` 
   - `sellers`

2. **Tablas de Productos**
   - `products`
   - `volume_discounts`

3. **Tablas de Órdenes**
   - `orders`
   - `order_items`
   - `payments`

4. **Tablas de Carrito**
   - `shopping_carts`
   - `cart_items`

5. **Tablas Secundarias**
   - `ratings`, `chats`, `messages`, etc.

## 📋 Resultados Esperados

### Salida de Consola Típica
```
[15:48:39] ✅ Conexiones establecidas correctamente
[15:48:39] 🚀 INICIANDO MIGRACIÓN SQLite → MySQL
[15:48:39] ===================================================
[15:48:39] ✅ Conexión SQLite verificada
[15:48:39] ✅ Conexión MySQL verificada
[15:48:39] 📋 Encontradas 43 tablas en SQLite
[15:48:39] 🔄 Migrando tabla: users
[15:48:39]    📊 Migradas 30/30 filas
[15:48:39]    ✅ Tabla users migrada exitosamente (30 filas)
...
[15:48:40] ✅ MIGRACIÓN COMPLETADA EXITOSAMENTE
```

### Estadísticas de Éxito Típicas
- **Tablas procesadas**: 43
- **Tablas exitosas**: 42+
- **Filas migradas**: 1400+
- **Tasa de éxito**: 97%+

## 📄 Archivos Generados

### 1. Reporte de Migración
**Ubicación**: `database/SQLITE_MYSQL_MIGRATION_REPORT.md`

Contiene:
- ✅ Lista detallada de tablas migradas
- 📊 Conteo de filas por tabla
- 📈 Estadísticas de éxito
- 📝 Log completo de la migración

### 2. Logs en Consola
- Progreso en tiempo real
- Indicadores visuales (✅ ❌ ⚠️)
- Conteos de filas migradas
- Mensajes de error detallados

## 🔍 Verificación Post-Migración

### Comandos de Verificación
```bash
# Verificar conteos de tablas principales
php artisan tinker --execute "
echo 'Users: ' . \App\Models\User::count() . PHP_EOL;
echo 'Products: ' . \App\Models\Product::count() . PHP_EOL;
echo 'Orders: ' . \App\Models\Order::count() . PHP_EOL;
echo 'Categories: ' . \App\Models\Category::count() . PHP_EOL;
"

# Verificar estado de migraciones
php artisan migrate:status
```

### Verificación Manual
```sql
-- Conectar a MySQL y verificar datos
USE tu_base_datos;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_products FROM products;  
SELECT COUNT(*) as total_orders FROM orders;
```

## ⚠️ Troubleshooting

### Errores Comunes

#### 1. "Connection refused" 
```bash
❌ Error de conexión: Connection refused
```
**Solución**: Verificar que MySQL esté ejecutándose y las credenciales sean correctas.

#### 2. "Base table or view not found"
```bash
❌ SQLSTATE[42S02]: Base table or view not found
```
**Solución**: Ejecutar migraciones primero:
```bash
php artisan migrate:fresh --force
```

#### 3. "Foreign key constraint fails"
```bash
❌ Error migrando tabla: foreign key constraint fails
```
**Solución**: El script maneja esto automáticamente deshabilitando foreign key checks.

#### 4. "Duplicate entry"
```bash
❌ SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
```
**Solución**: El script trunca tablas automáticamente. Si persiste, verificar índices únicos.

### Comandos de Diagnóstico
```bash
# Verificar conexión MySQL
mysql -u root -p -h 127.0.0.1 -e "SELECT 1;"

# Verificar archivo SQLite
ls -la database/database.sqlite

# Verificar configuración Laravel
php artisan config:show database
```

## 🔧 Personalización

### Cambiar Tamaño de Lotes
```php
// Línea ~144 en el script
$batchSize = 1000; // Cambiar de 500 a 1000
```

### Agregar Tablas Prioritarias
```php
// Línea ~85-95 en el script
$priorityTables = [
    'users',
    'categories', 
    'sellers',
    'tu_tabla_personalizada', // Agregar aquí
    'products',
    // ...
];
```

### Personalizar Mensajes de Log
```php
// Método log() línea ~300+
private function log($message)
{
    $timestamp = date('H:i:s');
    $formattedMessage = "[{$timestamp}] {$message}";
    echo $formattedMessage . PHP_EOL;
}
```

## 💡 Consejos de Rendimiento

1. **Ejecutar en horas de bajo tráfico** para evitar bloqueos
2. **Hacer backup de SQLite** antes de migrar:
   ```bash
   cp database/database.sqlite database/database.sqlite.backup
   ```
3. **Verificar espacio en disco** - MySQL requiere más espacio que SQLite
4. **Monitorear memoria RAM** durante tablas grandes (>10k filas)

## 📞 Soporte

### En caso de problemas:
1. 📄 Revisar el reporte generado en `database/SQLITE_MYSQL_MIGRATION_REPORT.md`
2. 🔍 Verificar logs de Laravel en `storage/logs/`
3. 🗃️ Verificar logs de MySQL
4. 🔄 Re-ejecutar solo las tablas fallidas modificando el script

### Script para Re-migración de Tabla Específica
```php
// Agregar al final del script para re-migrar tabla específica
if ($argc > 1 && $argv[1]) {
    $specificTable = $argv[1];
    $migrator = new SQLiteToMySQLMigrator();
    $migrator->migrateTable($specificTable);
}
```

Uso:
```bash
php database/scripts/migrate_sqlite_to_mysql.php users
```

---

## 🎯 Resumen de Uso

```bash
# 1. Verificar prerequisitos
php artisan migrate:status

# 2. Hacer backup
cp database/database.sqlite database/database.sqlite.backup

# 3. Ejecutar migración  
php database/scripts/migrate_sqlite_to_mysql.php

# 4. Verificar resultados
cat database/SQLITE_MYSQL_MIGRATION_REPORT.md

# 5. Probar aplicación
php artisan serve
```

¡La migración debe completarse exitosamente en menos de 1 minuto para bases de datos típicas!