<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? config('app.name') }}</title>
    <style>
        /* Reset styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background-color: #f7fafc;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .header {
            background: linear-gradient(135deg, {{ $headerColor ?? '#007bff' }}, {{ $headerColorSecondary ?? '#0056b3' }});
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        
        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 8px;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 24px;
            letter-spacing: -0.025em;
        }
        
        .message {
            font-size: 16px;
            line-height: 1.7;
            color: #4a5568;
            margin-bottom: 32px;
        }
        
        .message p {
            margin-bottom: 16px;
        }
        
        .message ul {
            margin: 16px 0;
            padding-left: 24px;
        }
        
        .message li {
            margin-bottom: 8px;
            color: #718096;
        }
        
        .cta-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .cta-button {
            display: inline-block;
            padding: 14px 28px;
            background-color: {{ $ctaColor ?? '#007bff' }};
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.025em;
            transition: all 0.2s ease;
        }
        
        .cta-button:hover {
            background-color: {{ $ctaColorHover ?? '#0056b3' }};
        }
        
        .alert {
            padding: 20px;
            border-radius: 6px;
            margin: 32px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .alert-info {
            background-color: #ebf8ff;
            border-left: 3px solid #3182ce;
            color: #2c5282;
        }
        
        .alert-warning {
            background-color: #fffaf0;
            border-left: 3px solid #ed8936;
            color: #c05621;
        }
        
        .alert-success {
            background-color: #f0fff4;
            border-left: 3px solid #38a169;
            color: #276749;
        }
        
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            border-top: 1px solid #dee2e6;
        }
        
        .footer p {
            margin: 8px 0;
        }
        
        .footer .social-links {
            margin: 20px 0;
        }
        
        .footer .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #6c757d;
            text-decoration: none;
        }
        
        .url-break {
            word-break: break-all;
            color: #007bff;
            font-size: 14px;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .container {
                margin: 10px;
                border-radius: 8px;
            }
            
            .content {
                padding: 25px 20px;
            }
            
            .header {
                padding: 25px 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .cta-button {
                padding: 14px 28px;
                font-size: 15px;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>{{ $appName ?? config('app.name') }}</h1>
            @if(isset($headerSubtitle))
                <div class="subtitle">{{ $headerSubtitle }}</div>
            @endif
        </header>
        
        <main class="content">
            @yield('content')
        </main>
        
        <footer class="footer">
            @yield('footer')
            
            @if(!isset($hideDefaultFooter) || !$hideDefaultFooter)
                <div class="social-links">
                    @if(isset($websiteUrl))
                        <a href="{{ $websiteUrl }}">Sitio Web</a>
                    @endif
                    @if(isset($supportEmail))
                        <a href="mailto:{{ $supportEmail }}">Soporte</a>
                    @endif
                </div>
                
                <p>Este es un mensaje automático, por favor no responder a este correo.</p>
                <p>&copy; {{ date('Y') }} {{ $appName ?? config('app.name') }}. Todos los derechos reservados.</p>
            @endif
        </footer>
    </div>
</body>
</html>