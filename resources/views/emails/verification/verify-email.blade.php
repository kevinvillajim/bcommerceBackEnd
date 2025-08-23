@extends('emails.layouts.base', [
    'headerColor' => '#28a745',
    'headerColorSecondary' => '#198754',
    'ctaColor' => '#28a745',
    'ctaColorHover' => '#198754',
    'headerSubtitle' => 'Verificación de cuenta'
])

@section('content')
    <div class="greeting">
        Hola {{ $user->name }},
    </div>
    
    <div class="message">
        <p>Te damos la bienvenida a {{ $appName }}. Para completar tu registro y acceder a todas las funcionalidades de nuestra plataforma, necesitamos verificar tu dirección de correo electrónico.</p>
        
        <p>Una vez verificada tu cuenta podrás:</p>
        <ul>
            <li>Acceder a todas las funciones de la plataforma</li>
            <li>Realizar compras de forma segura</li>
            <li>Contactar con vendedores</li>
            <li>Recibir actualizaciones sobre tus pedidos</li>
        </ul>
    </div>
    
    <div class="cta-container">
        <a href="{{ $verificationUrl }}" class="cta-button">
            Verificar mi cuenta
        </a>
    </div>
    
    <div class="alert alert-warning">
        <strong>Importante:</strong> Este enlace expirará en {{ $expiresHours }} horas por motivos de seguridad.
    </div>
    
    <div class="message">
        <p><strong>¿El botón no funciona?</strong></p>
        <p>Si tienes problemas con el botón, puedes copiar y pegar el siguiente enlace directamente en tu navegador:</p>
        <p class="url-break">{{ $verificationUrl }}</p>
    </div>
    
    <div class="alert alert-info">
        <strong>Nota de seguridad:</strong> Si no creaste una cuenta en {{ $appName }}, puedes ignorar este correo de forma segura. Tu información permanecerá protegida.
    </div>
@endsection

@section('footer')
    <div style="background-color: #f0fff4; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 3px solid #38a169;">
        <p style="margin: 0; font-size: 14px; color: #276749; font-weight: 500;">
            Te recomendamos guardar este email hasta completar la verificación de tu cuenta.
        </p>
    </div>
@endsection