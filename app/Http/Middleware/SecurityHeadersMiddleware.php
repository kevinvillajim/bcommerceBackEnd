<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and add security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add security headers to HTML responses
        if (! $response->headers->has('Content-Type') ||
            ! str_contains($response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        // OPTIMIZADO: Habilitado con configuración flexible para Datafast
        $isDatafastRoute = str_contains($request->getUri(), '/datafast') ||
                          str_contains($request->getUri(), '/payment');

        // Solo desactivar CSP estricto en rutas de Datafast en desarrollo
        $skipCSP = config('app.env') === 'local' && $isDatafastRoute;

        // Get allowed origins from CORS config
        $frontendUrl = config('cors.allowed_origins')[0] ?? 'https://comersia.app';

        // Parse domain from URL
        $parsedUrl = parse_url($frontendUrl);
        $domain = $parsedUrl['host'] ?? 'comersia.app';

        // Content Security Policy - Restrictive but functional
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://apis.google.com https://www.google.com https://www.gstatic.com https://p11.techlab-cdn.com https://eu-test.oppwa.com https://eu-prod.oppwa.com https://www.datafast.com.ec http://localhost:* http://127.0.0.1:*",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://eu-test.oppwa.com https://eu-prod.oppwa.com https://www.datafast.com.ec",
            "img-src 'self' data: https: blob: http://localhost:* http://127.0.0.1:*",
            "font-src 'self' https://fonts.gstatic.com https://eu-test.oppwa.com https://eu-prod.oppwa.com",
            "connect-src 'self' https://api.comersia.app https://{$domain} https://apis.google.com https://eu-test.oppwa.com https://eu-prod.oppwa.com https://www.datafast.com.ec https://p11.techlab-cdn.com http://localhost:* http://127.0.0.1:*",
            "media-src 'self' data: blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https://eu-test.oppwa.com https://eu-prod.oppwa.com",
            "frame-src 'self' https://eu-test.oppwa.com https://eu-prod.oppwa.com",
            "frame-ancestors 'none'",
            'upgrade-insecure-requests',
        ];

        // Aplicar CSP solo si no es una ruta de Datafast en desarrollo
        if (!$skipCSP) {
            $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        }

        // Additional security headers (siempre aplicados excepto en Datafast)
        if (!$isDatafastRoute) {
            $response->headers->set('X-Frame-Options', 'DENY');
        } else {
            // Datafast necesita SAMEORIGIN para funcionar
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Strict Transport Security (only for HTTPS)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
