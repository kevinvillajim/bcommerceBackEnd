@extends('emails.layouts.base', [
    'headerColor' => '#007bff',
    'headerColorSecondary' => '#0056b3',
    'ctaColor' => '#007bff',
    'ctaColorHover' => '#0056b3',
    'headerSubtitle' => '¡Bienvenido a nuestra comunidad!'
])

@section('content')
    <div style="background: linear-gradient(135deg, #007bff, #28a745); color: white; padding: 30px; border-radius: 10px; text-align: center; margin: 20px 0;">
        <h2 style="margin: 0; font-size: 28px;">🎉 ¡Bienvenido {{ $user->name }}!</h2>
        <p style="margin: 10px 0 0 0; font-size: 18px; opacity: 0.95;">Tu cuenta ha sido creada exitosamente</p>
    </div>
    
    <div class="message">
        <p>¡Nos alegra tenerte como parte de nuestra comunidad! Tu registro en {{ $appName }} se ha completado correctamente y ya puedes disfrutar de todas nuestras funcionalidades.</p>
    </div>
    
    <div style="background-color: #f8f9fa; padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #007bff;">
        <h3 style="color: #007bff; margin-top: 0;">🚀 ¿Qué puedes hacer ahora?</h3>
        
        <div style="display: grid; gap: 15px;">
            <div style="display: flex; align-items: center;">
                <span style="font-size: 24px; margin-right: 10px;">🛍️</span>
                <div>
                    <strong>Explorar productos</strong><br>
                    <small style="color: #6c757d;">Descubre miles de productos de vendedores verificados</small>
                </div>
            </div>
            
            <div style="display: flex; align-items: center;">
                <span style="font-size: 24px; margin-right: 10px;">❤️</span>
                <div>
                    <strong>Crear listas de favoritos</strong><br>
                    <small style="color: #6c757d;">Guarda los productos que más te gusten para encontrarlos fácilmente</small>
                </div>
            </div>
            
            <div style="display: flex; align-items: center;">
                <span style="font-size: 24px; margin-right: 10px;">💬</span>
                <div>
                    <strong>Contactar vendedores</strong><br>
                    <small style="color: #6c757d;">Haz preguntas sobre productos y resuelve tus dudas</small>
                </div>
            </div>
            
            <div style="display: flex; align-items: center;">
                <span style="font-size: 24px; margin-right: 10px;">🛒</span>
                <div>
                    <strong>Realizar pedidos</strong><br>
                    <small style="color: #6c757d;">Compra de forma segura con múltiples métodos de pago</small>
                </div>
            </div>
            
            <div style="display: flex; align-items: center;">
                <span style="font-size: 24px; margin-right: 10px;">📦</span>
                <div>
                    <strong>Seguir tus pedidos</strong><br>
                    <small style="color: #6c757d;">Mantente al día con el estado de tus compras en tiempo real</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="cta-container">
        <a href="{{ $appUrl }}" class="cta-button">
            🚀 Comenzar a explorar
        </a>
    </div>
    
    <div class="alert alert-success">
        <strong>💡 Consejo:</strong> Completa tu perfil para obtener mejores recomendaciones de productos personalizadas.
    </div>
    
    <div class="message">
        <p>Si tienes alguna pregunta o necesitas ayuda para comenzar, no dudes en contactarnos. ¡Estamos aquí para ayudarte a tener la mejor experiencia posible!</p>
    </div>
@endsection

@section('footer')
    <div style="background-color: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center;">
        <h4 style="color: #1976d2; margin-top: 0;">📧 ¿Necesitas ayuda?</h4>
        <p style="margin: 0; font-size: 14px; color: #1565c0;">
            Visita nuestro centro de ayuda o contacta a nuestro equipo de soporte.<br>
            Estamos disponibles de lunes a viernes de 9:00 AM a 6:00 PM.
        </p>
    </div>
    
    <p style="margin: 15px 0 0 0; font-size: 16px; font-weight: 600; color: #007bff;">
        ¡Gracias por unirte a {{ $appName }}! 🎊
    </p>
@endsection