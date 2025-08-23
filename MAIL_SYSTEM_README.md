# 📧 Sistema de Emails Mejorado - BCommerce

## 🚀 Introducción

El sistema de emails de BCommerce ha sido completamente refactorizado para ser más **mantenible**, **escalable** y **fácil de personalizar**. El nuevo sistema utiliza templates Blade individuales, clases Mailable separadas y un patrón de arquitectura limpia.

## 🏗️ Arquitectura del Sistema

### Estructura de Directorios

```
app/
├── Mail/                           # Clases Mailable individuales
│   ├── BaseMail.php               # Clase base con funcionalidad común
│   ├── EmailVerificationMail.php  # Email de verificación
│   ├── PasswordResetMail.php      # Email de recuperación
│   ├── WelcomeMail.php            # Email de bienvenida
│   ├── NotificationMail.php       # Email de notificaciones
│   └── OrderConfirmationMail.php  # Email de confirmación de orden
├── Services/
│   ├── Mail/
│   │   └── MailManager.php        # Gestor principal de emails
│   └── MailServiceAdapter.php     # Adaptador para compatibilidad
└── Providers/
    └── MailServiceProvider.php    # Proveedor de servicios

resources/views/emails/
├── layouts/
│   └── base.blade.php             # Layout base responsive
├── verification/
│   └── verify-email.blade.php     # Template de verificación
├── password/
│   └── reset.blade.php            # Template de recuperación
├── welcome/
│   └── new-user.blade.php         # Template de bienvenida
├── notification/
│   └── general.blade.php          # Template de notificaciones
├── orders/
│   └── confirmation.blade.php     # Template de confirmación
└── test.blade.php                 # Template para pruebas

config/
└── emails.php                     # Configuración de emails
```

## ✨ Características Principales

### 🎨 Templates Blade Personalizables
- **Templates individuales** para cada tipo de email
- **Layout base responsive** con diseño moderno
- **Fácil personalización** de colores, contenido y estructura
- **Soporte para múltiples temas** y configuraciones

### 🔧 Clases Mailable Individuales
- **Separación de responsabilidades** por tipo de email
- **Reutilización de código** con clase base
- **Configuración automática** de SMTP desde base de datos
- **Fácil testing** y mantenimiento

### ⚙️ Sistema de Configuración
- **Configuración centralizada** en `config/emails.php`
- **Múltiples temas de colores**
- **Características toggleables** por template
- **Configuración de contenido** flexible

## 📋 Tipos de Email Disponibles

| Tipo | Descripción | Template | Clase Mailable |
|------|-------------|----------|----------------|
| `verification` | Verificación de cuenta | `emails.verification.verify-email` | `EmailVerificationMail` |
| `password_reset` | Recuperación de contraseña | `emails.password.reset` | `PasswordResetMail` |
| `welcome` | Bienvenida a nuevos usuarios | `emails.welcome.new-user` | `WelcomeMail` |
| `notification` | Notificaciones generales | `emails.notification.general` | `NotificationMail` |
| `order_confirmation` | Confirmación de pedidos | `emails.orders.confirmation` | `OrderConfirmationMail` |

## 🚀 Cómo Usar el Sistema

### Enviar Email de Verificación
```php
use App\Services\Mail\MailManager;

$mailManager = app(MailManager::class);
$success = $mailManager->sendVerificationEmail($user, $token);
```

### Enviar Email de Notificación
```php
$success = $mailManager->sendNotificationEmail(
    $user, 
    '🎉 Nueva función disponible', 
    'Te presentamos nuestra nueva función de recomendaciones personalizadas...',
    'announcement',
    [
        'action_url' => 'https://app.com/features/recommendations',
        'action_text' => 'Explorar función',
        'priority' => 'high'
    ]
);
```

### Usar el Sistema Legacy (Compatibilidad)
```php
use App\Services\MailService;

// El código existente sigue funcionando sin cambios
$mailService = app(MailService::class);
$success = $mailService->sendWelcomeEmail($user);
```

## 🎨 Personalización de Templates

### Modificar un Template Existente
1. Navega a `resources/views/emails/[tipo]/[template].blade.php`
2. Edita el contenido manteniendo la estructura base
3. Los cambios se aplican inmediatamente

### Crear un Nuevo Template
1. Crea el archivo Blade en el directorio apropiado
2. Extiende el layout base: `@extends('emails.layouts.base')`
3. Personaliza colores y contenido usando variables

Ejemplo de template personalizado:
```blade
@extends('emails.layouts.base', [
    'headerColor' => '#6f42c1',
    'ctaColor' => '#6f42c1',
    'headerSubtitle' => 'Promoción especial'
])

@section('content')
    <div class="greeting">¡Hola {{ $user->name }}!</div>
    <div class="message">
        <p>Contenido de tu email personalizado...</p>
    </div>
@endsection
```

## ⚙️ Configuración del Sistema

### Archivo de Configuración (`config/emails.php`)

```php
'themes' => [
    'default' => [
        'primary_color' => '#007bff',
        'success_color' => '#28a745',
        // ... más colores
    ],
],

'templates' => [
    'verification' => [
        'enabled' => true,
        'theme' => 'green',
        'expires_hours' => 24,
        'features' => [
            'show_security_tips' => true,
        ],
    ],
],
```

### Variables Disponibles en Templates

Todas las templates tienen acceso a estas variables:
- `$appName` - Nombre de la aplicación
- `$appUrl` - URL base de la aplicación
- `$user` - Objeto usuario destinatario
- `$supportEmail` - Email de soporte
- `$websiteUrl` - URL del sitio web

## 🔧 Comandos Artisan

### Verificar Estado del Sistema
```bash
php artisan email:status
```

### Probar Conexión SMTP
```bash
php artisan email:status --test
```

### Listar Templates Disponibles
```bash
php artisan email:status --templates
```

## 🆕 Agregar Nuevos Tipos de Email

### 1. Crear la Clase Mailable
```php
<?php
namespace App\Mail;

class CustomMail extends BaseMail
{
    protected function getTemplateName(): string
    {
        return 'emails.custom.template';
    }
    
    protected function getSubject(): string
    {
        return 'Asunto personalizado';
    }
}
```

### 2. Crear el Template Blade
```blade
@extends('emails.layouts.base')

@section('content')
    <!-- Tu contenido aquí -->
@endsection
```

### 3. Agregar al MailManager
```php
public function sendCustomEmail(User $user, array $data): bool
{
    try {
        Mail::send(new CustomMail($user, $data));
        return true;
    } catch (\Exception $e) {
        Log::error('Failed to send custom email', ['error' => $e->getMessage()]);
        return false;
    }
}
```

## 🔄 Migración desde el Sistema Anterior

El sistema mantiene **100% compatibilidad** con el código existente a través del `MailServiceAdapter`. No se requieren cambios en controladores o servicios existentes.

### Beneficios de Migrar
- ✅ **Mantenibilidad**: Templates Blade en lugar de HTML hardcodeado
- ✅ **Escalabilidad**: Fácil agregar nuevos tipos de email
- ✅ **Personalización**: Modificar diseños sin tocar código PHP
- ✅ **Testing**: Clases individuales más fáciles de testear
- ✅ **Performance**: Mejor gestión de recursos y caché

## 🛠️ Desarrollo y Testing

### Testing de Templates
```bash
# Probar conexión SMTP
php artisan email:status --test

# Verificar templates disponibles
php artisan email:status --templates
```

### Logs de Email
Todos los emails se registran automáticamente en los logs de Laravel:
```
[2024-XX-XX] local.INFO: Verification email sent successfully {"user_id":1,"email":"user@example.com","template":"emails.verification.verify-email"}
```

## 📞 Soporte

Para problemas o preguntas sobre el sistema de emails:
1. Revisar logs en `storage/logs/laravel.log`
2. Usar `php artisan email:status --test` para diagnosticar problemas de SMTP
3. Verificar configuración en base de datos (tabla `configurations`)

---

**¡El sistema de emails ahora es más potente, flexible y fácil de mantener!** 🚀