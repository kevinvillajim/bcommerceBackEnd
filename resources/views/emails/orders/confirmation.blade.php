@extends('emails.layouts.base', [
    'headerColor' => '#28a745',
    'headerColorSecondary' => '#198754',
    'ctaColor' => '#28a745',
    'ctaColorHover' => '#198754',
    'headerSubtitle' => 'Confirmación de pedido'
])

@section('content')
    <div class="greeting">
        ¡Hola {{ $user->name }}!
    </div>
    
    <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 25px; border-radius: 10px; text-align: center; margin: 20px 0;">
        <h2 style="margin: 0; font-size: 24px;">✅ ¡Pedido confirmado!</h2>
        <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.95;">Orden #{{ $order->id }}</p>
    </div>
    
    <div class="message">
        <p>Tu pedido ha sido recibido y confirmado exitosamente. Te mantendremos informado sobre el estado de tu compra.</p>
    </div>
    
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 10px; margin: 25px 0;">
        <h3 style="color: #333; margin-top: 0;">📦 Resumen del pedido</h3>
        
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="background-color: #e9ecef;">
                <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Producto</th>
                <th style="padding: 10px; text-align: center; border-bottom: 2px solid #dee2e6;">Cantidad</th>
                <th style="padding: 10px; text-align: right; border-bottom: 2px solid #dee2e6;">Precio</th>
            </tr>
            @foreach($order->items as $item)
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">{{ $item->product->name }}</td>
                <td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">{{ $item->quantity }}</td>
                <td style="padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6;">${{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
            <tr style="font-weight: bold; background-color: #f8f9fa;">
                <td colspan="2" style="padding: 15px; text-align: right; border-top: 2px solid #28a745;">Total:</td>
                <td style="padding: 15px; text-align: right; border-top: 2px solid #28a745; color: #28a745;">${{ number_format($order->total, 2) }}</td>
            </tr>
        </table>
    </div>
    
    <div class="cta-container">
        <a href="{{ $orderUrl }}" class="cta-button">
            📋 Ver detalles del pedido
        </a>
    </div>
    
    <div class="alert alert-info">
        <strong>📧 Actualizaciones:</strong> Te enviaremos notificaciones por email sobre el estado de tu pedido.
    </div>
@endsection

@section('footer')
    <div style="background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <p style="margin: 0; font-size: 13px; color: #155724;">
            <strong>💡 Tip:</strong> Puedes seguir el estado de tu pedido en cualquier momento desde tu cuenta.
        </p>
    </div>
@endsection