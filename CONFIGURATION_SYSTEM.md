# Sistema de Configuración Robusto

## Resumen

El sistema de configuración de BCommerce ha sido completamente rediseñado para ser **resistente a fallos** y garantizar que la aplicación funcione incluso cuando ciertos componentes no están disponibles.

## Arquitectura

### Niveles de Fallback

El sistema implementa **5 niveles de fallback** en orden de prioridad:

1. **Caché en memoria** (Request-scoped)
2. **Caché Laravel** (Redis/File/Database)
3. **Base de datos** (tabla `configurations`)
4. **Variables de entorno** (.env)
5. **Valores por defecto hardcodeados**

### Componentes

#### 1. RobustConfigurationService
- Servicio principal con manejo robusto de errores
- Detecta automáticamente la disponibilidad de componentes
- Implementa fallbacks inteligentes
- Logging comprehensivo para debugging

#### 2. ConfigurationService
- Facade que mantiene compatibilidad hacia atrás
- Delega todas las operaciones al RobustConfigurationService

#### 3. EnsureConfigurationAvailable Middleware
- Protege endpoints críticos
- Verifica el estado del sistema antes de procesar requests

## Problemas Resueltos

### 1. Dependencia de Base de Datos en Bootstrap
**Problema**: El sistema fallaba cuando la base de datos no estaba disponible durante el bootstrap de Laravel.

**Solución**: Detección automática de disponibilidad con fallback a variables de entorno.

### 2. Errores en Envío de Correos
**Problema**: El sistema de mail fallaba si no podía cargar configuración desde la base de datos.

**Solución**: 
- BaseMail ahora maneja errores gracefully
- Variable `MAIL_USE_ENV_ONLY=true` para forzar uso de .env

### 3. Cache Circular Dependencies
**Problema**: El cache driver `database` causaba dependencias circulares.

**Solución**: Detección inteligente del tipo de cache y fallback cuando es necesario.

## Uso

### Configuración Básica

```php
// Obtener configuración con fallback
$value = $configService->getConfig('email.smtpHost', 'default.smtp.com');

// Establecer configuración
$configService->setConfig('business.taxRate', 0.15);

// Limpiar caché
$configService->clearCache();
```

### Diagnóstico

```bash
# Diagnosticar el sistema completo
php artisan config:diagnose

# Diagnosticar y reparar problemas
php artisan config:diagnose --fix

# Test completo de todas las configuraciones
php artisan config:diagnose --test-all
```

### Variables de Entorno Importantes

```env
# Forzar uso solo de variables de entorno (sin base de datos)
MAIL_USE_ENV_ONLY=true

# Configuración de mail (usado como fallback)
MAIL_HOST=mail.comersia.app
MAIL_PORT=465
MAIL_USERNAME=info@comersia.app
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=info@comersia.app
MAIL_FROM_NAME="Comersia App"
```

## Mejores Prácticas

### 1. Para Desarrollo

- Usa `php artisan config:diagnose` regularmente
- Mantén las variables de entorno actualizadas como fallback
- Usa cache driver `file` o `redis` en lugar de `database`

### 2. Para Producción

```env
# Configuración recomendada
APP_ENV=production
APP_DEBUG=false
CACHE_DRIVER=redis
MAIL_USE_ENV_ONLY=true
```

### 3. Monitoreo

Logs importantes a monitorear:

- `"Database not available for configuration service"`
- `"Configuration middleware diagnostics"`
- `"Using .env mail configuration"`

## Troubleshooting

### Problema: "Configuration system temporarily unavailable"

**Causa**: Base de datos no disponible para operación crítica.

**Solución**:
1. Verificar credenciales de base de datos
2. Ejecutar `php artisan config:diagnose --fix`
3. Verificar logs en `storage/logs/laravel.log`

### Problema: Correos no se envían

**Causa**: Configuración de mail no disponible.

**Solución**:
1. Establecer `MAIL_USE_ENV_ONLY=true` en .env
2. Verificar variables MAIL_* en .env
3. Ejecutar `php artisan mail:debug --test-send`

### Problema: Valores de configuración incorrectos

**Causa**: Caché obsoleta o conflicto entre fuentes.

**Solución**:
```bash
php artisan cache:clear
php artisan config:clear
php artisan config:diagnose --fix
```

## Testing

### Test Unitario

```php
use App\Services\ConfigurationService;

public function test_configuration_fallback()
{
    $service = new ConfigurationService();
    
    // Debe retornar default cuando no existe
    $value = $service->getConfig('non.existent.key', 'default');
    $this->assertEquals('default', $value);
    
    // Debe obtener valor correcto
    $value = $service->getConfig('email.smtpHost');
    $this->assertNotNull($value);
}
```

### Test de Integración

```bash
# Test completo del sistema
php artisan test --filter=ConfigurationTest

# Test de mail con configuración robusta
php artisan mail:debug --test-send --email=test@example.com
```

## Migración desde Sistema Anterior

Si estás actualizando desde el sistema anterior:

1. **Backup de configuraciones**:
```sql
SELECT * FROM configurations;
```

2. **Actualizar archivos**:
- Reemplazar `ConfigurationService.php`
- Agregar `RobustConfigurationService.php`
- Actualizar `BaseMail.php`

3. **Limpiar caché**:
```bash
php artisan cache:clear
php artisan config:clear
```

4. **Verificar sistema**:
```bash
php artisan config:diagnose
```

## Mantenimiento

### Limpieza Regular

```bash
# Cron job recomendado (diario)
0 3 * * * cd /path/to/app && php artisan config:diagnose --fix >> /dev/null 2>&1
```

### Monitoreo de Salud

```php
// Health check endpoint
Route::get('/health/config', function () {
    $service = app(ConfigurationService::class);
    $diagnostics = $service->getDiagnostics();
    
    return response()->json([
        'healthy' => $diagnostics['database_available'] || $diagnostics['cache_available'],
        'diagnostics' => $diagnostics
    ]);
});
```

## Conclusión

El nuevo sistema de configuración garantiza que tu aplicación **nunca falle** debido a problemas de configuración. Con múltiples niveles de fallback y diagnóstico comprehensivo, puedes estar seguro de que tu aplicación seguirá funcionando incluso en las condiciones más adversas.