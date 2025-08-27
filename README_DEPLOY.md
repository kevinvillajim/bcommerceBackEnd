# 🚀 Sistema de Deployment BCommerce

## 📋 Descripción

Este sistema permite cambiar rápidamente entre configuraciones de entorno (desarrollo vs producción) con un solo comando, eliminando errores de configuración manual y acelerando deployments.

## 🛠️ Archivos del Sistema

### Configuraciones de Entorno
- `.env.local` - Configuración para desarrollo local
- `.env.production` - Configuración para staging/producción
- `.env` - Archivo activo (se sobrescribe automáticamente)
- `.env.backup_*` - Respaldos automáticos

### Scripts
- `deploy.sh` - Script principal de deployment
- `README_DEPLOY.md` - Esta documentación

## 🎯 Uso Básico

### Ver Estado Actual
```bash
./deploy.sh status
```

### Cambiar a Desarrollo
```bash
./deploy.sh local
```

### Cambiar a Producción/Staging
```bash
./deploy.sh production
```

### Ver Ayuda
```bash
./deploy.sh help
```

## 🔄 Workflow Típico

### Para Desarrollo Local
```bash
# 1. Cambiar a local
./deploy.sh local

# 2. Iniciar servicios
php artisan serve
npm run dev  # (en directorio del frontend)
php artisan queue:work
```

### Para Deployment a Staging/Producción
```bash
# 1. Cambiar a producción
./deploy.sh production

# 2. Verificar configuración
./deploy.sh status

# 3. Migraciones si es necesario
php artisan migrate

# 4. Optimizaciones adicionales (opcional)
php artisan optimize
```

## ⚙️ Configuraciones Específicas

### Desarrollo Local (.env.local)
- `APP_ENV=local`
- `APP_DEBUG=true` 
- URLs localhost
- Base de datos local
- CORS permisivo
- Logs detallados
- Cookies no seguras

### Producción (.env.production)
- `APP_ENV=production`
- `APP_DEBUG=false`
- URLs de comersia.app
- Base de datos de producción
- CORS restrictivo
- Logs solo errores
- Cookies seguras con HTTPS

## 🔐 Variables Importantes a Configurar

### Obligatorias para Producción
```env
# DeUna - CRÍTICO
DEUNA_WEBHOOK_SECRET=tu_webhook_secret_real

# Datafast - Si usas producción
DATAFAST_PRODUCTION_ENTITY_ID=tu_entity_id
DATAFAST_PRODUCTION_AUTHORIZATION=tu_authorization
DATAFAST_PRODUCTION_MID=tu_mid
DATAFAST_PRODUCTION_TID=tu_tid
```

## 🛡️ Medidas de Seguridad

### Backups Automáticos
- Cada cambio de entorno crea backup: `.env.backup_YYYYMMDD_HHMMSS`
- Los backups se conservan automáticamente

### Validaciones
- Verifica existencia de archivos antes de aplicar
- Maneja errores de cache graciosamente
- Muestra estado después de cada cambio

### Git Integration
- Los archivos `.env.*` están en `.gitignore`
- Solo se trackea `.env.example` como template

## 🔧 Troubleshooting

### Error: "Archivo no encontrado"
```bash
# Verificar archivos existentes
./deploy.sh status

# Recrear archivos si es necesario
cp .env .env.local    # Copiar actual como local
cp .env .env.production  # Editar luego manualmente
```

### Error: "Config cache failed"
Este es normal si hay closures en config. El script continúa sin cache.

### Error: "Permission denied"
```bash
chmod +x deploy.sh
```

### Restaurar desde Backup
```bash
# Listar backups
ls .env.backup_*

# Restaurar específico
cp .env.backup_20250826_162203 .env
```

## 🎨 Características del Script

### Interfaz Visual
- ✅ Colores y emojis para claridad
- 📊 Estado detallado del entorno
- ⚠️ Advertencias importantes
- 🔄 Progress feedback

### Optimizaciones
- **Local**: Limpia cache para desarrollo fluido
- **Production**: Cachea configs para mejor performance
- **Manejo de errores**: Continúa aunque falle cache

### Información Contextual
- URLs actuales
- Base de datos activa
- Archivos disponibles
- Comandos sugeridos

## 💡 Tips de Uso

### Desarrollo Diario
```bash
# Inicio del día
./deploy.sh local
php artisan serve

# Fin del día - test en producción
./deploy.sh production
php artisan optimize
```

### Deploy Rápido
```bash
# Un solo comando cambia todo el entorno
./deploy.sh production && php artisan migrate
```

### Verificación Rápida
```bash
# Ver qué entorno está activo
./deploy.sh status | grep "Entorno:"
```

## 🚨 Importante para Producción

1. **DEUNA_WEBHOOK_SECRET** debe ser el valor real del dashboard
2. **Verificar URLs** en `.env.production` antes del deploy
3. **Migraciones** ejecutar manualmente si es necesario
4. **Permisos** verificar que el servidor web pueda escribir logs
5. **SSL** debe estar configurado para cookies seguras

Este sistema está diseñado para eliminar errores humanos y acelerar deployments seguros.