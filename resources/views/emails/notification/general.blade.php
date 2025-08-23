@php
    $typeColors = [
        'notification' => ['primary' => '#007bff', 'secondary' => '#0056b3'],
        'announcement' => ['primary' => '#28a745', 'secondary' => '#198754'],
        'warning' => ['primary' => '#ffc107', 'secondary' => '#e0a800'],
        'urgent' => ['primary' => '#dc3545', 'secondary' => '#c82333'],
        'info' => ['primary' => '#17a2b8', 'secondary' => '#138496'],
    ];
    
    $colors = $typeColors[$emailType] ?? $typeColors['notification'];
    
    $typeIcons = [
        'notification' => '',
        'announcement' => '', 
        'warning' => '',
        'urgent' => '',
        'info' => '',
    ];
    
    $icon = $typeIcons[$emailType] ?? $typeIcons['notification'];
    
    $typeLabels = [
        'notification' => 'Notificación',
        'announcement' => 'Anuncio importante',
        'warning' => 'Advertencia',
        'urgent' => 'Mensaje urgente',
        'info' => 'Información',
    ];
    
    $typeLabel = $typeLabels[$emailType] ?? $typeLabels['notification'];
@endphp

@extends('emails.layouts.base', [
    'headerColor' => $colors['primary'],
    'headerColorSecondary' => $colors['secondary'],
    'ctaColor' => $colors['primary'],
    'ctaColorHover' => $colors['secondary'],
    'headerSubtitle' => $typeLabel
])

@section('content')
    <div class="greeting">
        {{ $subject }}
    </div>
    
    @if(isset($priority) && $priority === 'high')
        <div class="alert alert-warning">
            <strong>Alta prioridad:</strong> Este mensaje requiere tu atención inmediata.
        </div>
    @endif
    
    <div class="message">
        {!! nl2br(e($message)) !!}
    </div>
    
    @if(isset($actionUrl) && isset($actionText))
        <div class="cta-container">
            <a href="{{ $actionUrl }}" class="cta-button">
                {{ $actionText }}
            </a>
        </div>
    @endif
    
    @if(isset($additionalInfo) && !empty($additionalInfo))
        <div style="background-color: #f7fafc; padding: 20px; border-radius: 6px; margin: 32px 0; border-left: 3px solid {{ $colors['primary'] }};">
            <h4 style="color: {{ $colors['primary'] }}; margin-top: 0; font-size: 16px; font-weight: 600;">Información adicional</h4>
            @foreach($additionalInfo as $key => $value)
                <p style="margin: 8px 0; font-size: 14px; color: #4a5568;"><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</p>
            @endforeach
        </div>
    @endif
    
    @if($sentByAdmin)
        <div class="alert alert-info">
            <strong>Remitente:</strong> Este mensaje fue enviado por el administrador: {{ $adminName }}
            @if($adminEmail)
                ({{ $adminEmail }})
            @endif
        </div>
    @endif
@endsection

@section('footer')
    @if($emailType === 'urgent')
        <div style="background-color: #fed7d7; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 3px solid #e53e3e;">
            <p style="margin: 0; font-size: 14px; color: #742a2a; font-weight: 500;">
                <strong>Mensaje urgente:</strong> Si necesitas ayuda inmediata, contacta a nuestro soporte.
            </p>
        </div>
    @elseif($emailType === 'warning')
        <div style="background-color: #fffaf0; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 3px solid #ed8936;">
            <p style="margin: 0; font-size: 14px; color: #c05621; font-weight: 500;">
                <strong>Advertencia:</strong> Te recomendamos tomar las acciones necesarias mencionadas en este mensaje.
            </p>
        </div>
    @elseif($emailType === 'announcement')
        <div style="background-color: #f0fff4; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 3px solid #38a169;">
            <p style="margin: 0; font-size: 14px; color: #276749; font-weight: 500;">
                <strong>Anuncio:</strong> Mantente al día con las últimas novedades de {{ $appName }}.
            </p>
        </div>
    @endif
@endsection