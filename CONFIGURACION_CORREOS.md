# Configuración de Correos en Producción 📧

## 🔧 Configuración Automática por Ambiente

El sistema está configurado para usar automáticamente:
- **Local/Desarrollo**: `log` (correos van a `storage/logs/laravel.log`)
- **Producción**: `smtp` (correos se envían realmente)

## 📋 Pasos para Producción

### 1. Activar el Ambiente de Producción

En el archivo `.env` de producción, descomenta y configura:

```bash
# Cambiar ambiente
APP_ENV=production
APP_DEBUG=false

# URLs de producción
APP_URL=https://api.comersia.app
FRONTEND_URL=https://comersia.app
SESSION_DOMAIN=.comersia.app

# Base de datos de producción
DB_DATABASE=capacit3_comersia
DB_USERNAME=capacit3_kevinvillajim
DB_PASSWORD=dalcroze77aA@

# ✅ CONFIGURACIÓN DE CORREOS (OBLIGATORIO)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@comersia.app
MAIL_FROM_NAME="Comersia App"
```

### 2. Proveedores SMTP Recomendados

#### 🟢 Gmail (Recomendado para desarrollo)
```bash
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-app-password  # Generar en Google Account Security
MAIL_ENCRYPTION=tls
```

**Pasos para Gmail:**
1. Activar autenticación de 2 factores
2. Ir a Google Account Security
3. Generar "App Password" específica
4. Usar esa contraseña en `MAIL_PASSWORD`

#### 🔵 Mailtrap (Para testing)
```bash
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=tu-username-mailtrap
MAIL_PASSWORD=tu-password-mailtrap
MAIL_ENCRYPTION=tls
```

#### 🟡 SendGrid (Producción profesional)
```bash
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=tu-api-key-sendgrid
MAIL_ENCRYPTION=tls
```

#### 🟠 Amazon SES (Económico para volumen)
```bash
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=tu-access-key
AWS_SECRET_ACCESS_KEY=tu-secret-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS=verificado@tudominio.com
```

### 3. Configuración en Base de Datos

Tu aplicación usa **MailService** que obtiene configuraciones adicionales desde la base de datos a través de `ConfigurationService`.

Las configuraciones en BD sobresciben las del `.env`:

- `email.smtpHost`
- `email.smtpPort` 
- `email.smtpUsername`
- `email.smtpPassword`
- `email.smtpEncryption`
- `email.senderEmail`
- `email.senderName`

### 4. Verificar Configuración

Ejecuta este comando para probar la configuración:

```bash
php artisan tinker

# En el shell de tinker:
$mailService = app(\App\Services\MailService::class);
$result = $mailService->testConnection();
dd($result);
```

### 5. Comandos de Producción

```bash
# Limpiar cache de configuración
php artisan config:clear

# Cachear configuración para producción
php artisan config:cache

# Reiniciar queue workers si usas colas para emails
php artisan queue:restart
```

## 🚨 Problemas Comunes

### Error: "Connection could not be established"
- Verificar host y puerto
- Confirmar credenciales
- Revisar si el proveedor requiere App Password

### Error: "Authentication failed"
- Gmail: Generar App Password
- Otros: Verificar username/password

### Error: "Stream_socket_enable_crypto(): SSL operation failed"
- Cambiar `MAIL_ENCRYPTION=tls` a `ssl`
- O viceversa según el proveedor

### Error: "Connection timed out"
- Verificar firewall del servidor
- Confirmar que el puerto no está bloqueado

## 🧪 Testing de Correos

Para probar correos sin enviarlos realmente, puedes usar:

```bash
# En .env temporal
MAIL_MAILER=log

# Verificar logs
tail -f storage/logs/laravel.log
```

## 📊 Monitoreo

Los logs de correos se encuentran en:
- `storage/logs/laravel.log` 
- Buscar: `"Verification email sent"` o `"SMTP connection test"`

## 🔐 Seguridad

- ⚠️ **Nunca** subir credenciales reales a repositorios
- Usar App Passwords para Gmail
- Configurar SPF/DKIM en el dominio si usas email personalizado
- Rotar contraseñas regularmente

---

## 📝 Notas para el Desarrollador

- La configuración está en `config/mail.php`
- El servicio principal está en `app/Services/MailService.php`
- Los templates están incluidos en el mismo servicio
- Sistema de configuración dual: `.env` + base de datos

## 🏆 **Tu Arquitectura Híbrida (Recomendada)**

Tu aplicación usa un patrón **Configuración Híbrida** muy inteligente:

```
BD (editable en tiempo real) → .env (fallback) → hardcoded (último recurso)
```

### ✅ **Ventajas de tu enfoque:**
- **🎛️ Configurable en tiempo real** - Admin puede cambiar SMTP sin redeploy
- **🔒 Seguridad por capas** - Credenciales sensibles pueden estar en BD encriptadas
- **🌍 Multi-ambiente** - Diferentes configuraciones por ambiente
- **📊 Auditoría** - Cambios rastreables en BD
- **🔄 Rollback rápido** - Restaurar configuración previa desde admin
- **🚀 Zero downtime** - Sin reiniciar servidor

### ⚠️ **Consideraciones:**
- **🐣 Bootstrap problem** - Si BD falla, fallback a .env
- **🏃‍♂️ Performance** - Query extra por cada email (cacheado por MailService)
- **💾 Dependencia BD** - Migrations críticas

## 🎯 **Solución Implementada**

1. **MailService mejorado**: Ahora usa .env como fallback inteligente
2. **EmailConfigurationSeeder**: Migra automáticamente valores de .env a BD
3. **Configuración automática por ambiente**

## 🚀 **Para Producción**

### ✅ **Configuración Real (Ya implementada)**
```bash
# Configuración SMTP de Comersia (Local y Producción)
MAIL_MAILER=smtp
MAIL_HOST=mail.comersia.app
MAIL_PORT=465
MAIL_USERNAME=info@comersia.app
MAIL_PASSWORD=5uO!zkxrH*s!Ty
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=info@comersia.app
MAIL_FROM_NAME="Comersia App"
```

### Opción 2: Migrar a BD (Recomendado)
```bash
# 1. Configurar .env con valores reales
# 2. Ejecutar seeder para migrar a BD
php artisan db:seed --class=EmailConfigurationSeeder

# 3. Verificar configuración
php artisan tinker
$mailService = app(\App\Services\MailService::class);
dd($mailService->getMailConfiguration());
```

✅ **Con esta configuración mejorada:**
- **Local**: Usa BD si existe, sino .env, sino defaults
- **Producción**: Usa BD (actualizable desde admin) con .env como fallback
- **Flexibilidad total**: Cambiar configuración desde panel admin sin redeploy