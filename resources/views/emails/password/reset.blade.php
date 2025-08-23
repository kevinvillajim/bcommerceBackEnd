@extends('emails.layouts.base', [
    'headerColor' => '#dc3545',
    'headerColorSecondary' => '#c82333',
    'ctaColor' => '#dc3545',
    'ctaColorHover' => '#c82333',
    'headerSubtitle' => 'Restablecimiento de contraseña'
])

@section('content')
    <div class="greeting">
        Hola {{ $user->name }},
    </div>
    
    <div class="message">
        <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en {{ $appName }}.</p>
        
        <p>Si solicitaste este cambio, puedes crear una nueva contraseña haciendo clic en el botón de abajo. Si no realizaste esta solicitud, puedes ignorar este correo de forma segura.</p>
    </div>
    
    <div class="cta-container">
        <a href="{{ $resetUrl }}" class="cta-button">
            Restablecer contraseña
        </a>
    </div>
    
    <div class="alert alert-warning">
        <strong>Importante:</strong> Este enlace expirará en 1 hora por motivos de seguridad.
    </div>
    
    <div class="message">
        <p><strong>¿El botón no funciona?</strong></p>
        <p>Si tienes problemas con el botón, puedes copiar y pegar el siguiente enlace directamente en tu navegador:</p>
        <p class="url-break">{{ $resetUrl }}</p>
    </div>
    
    <div class="alert alert-info">
        <strong>Medidas de seguridad:</strong> 
        <ul>
            <li>Si no solicitaste restablecer tu contraseña, ignora este correo</li>
            <li>Tu contraseña actual no será cambiada hasta que completes el proceso</li>
            <li>Nunca compartas este enlace con otras personas</li>
        </ul>
    </div>
@endsection

@section('footer')
    <div style="background-color: #fed7d7; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 3px solid #e53e3e;">
        <p style="margin: 0; font-size: 14px; color: #742a2a; font-weight: 500;">
            <strong>Recordatorio de seguridad:</strong> Nunca compartas tu contraseña con nadie. Nuestro equipo nunca te pedirá tu contraseña por email.
        </p>
    </div>
@endsection