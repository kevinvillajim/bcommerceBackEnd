<?php

namespace App\Http\Middleware;

use App\Models\AdminLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class CriticalErrorLoggingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $startTime = microtime(true);

        try {
            $response = $next($request);

            // Solo loggear errores críticos (5xx) y algunos 4xx importantes
            if ($this->shouldLogResponse($response)) {
                $this->logResponse($request, $response, $startTime);
            }

            return $response;

        } catch (Throwable $exception) {
            // Loggear excepciones críticas
            $this->logException($request, $exception, $startTime);

            // Re-lanzar la excepción para que Laravel la maneje normalmente
            throw $exception;
        }
    }

    /**
     * Determinar si debemos loggear esta respuesta
     */
    private function shouldLogResponse($response): bool
    {
        $statusCode = $response->getStatusCode();

        // Solo loggear errores críticos
        return $statusCode >= 500 || // Errores de servidor
               $statusCode === 401 || // No autorizado (posible ataque)
               $statusCode === 403 || // Prohibido (posible ataque)
               $statusCode === 429;   // Rate limit exceeded
    }

    /**
     * Loggear respuesta de error
     */
    private function logResponse(Request $request, $response, float $startTime): void
    {
        $statusCode = $response->getStatusCode();
        $duration = microtime(true) - $startTime;

        try {
            $eventType = $this->getEventTypeFromStatusCode($statusCode);
            $message = $this->getMessageFromStatusCode($statusCode);

            $context = [
                'duration_ms' => round($duration * 1000, 2),
                'request_data' => $this->getSafeRequestData($request),
                'response_size' => strlen($response->getContent() ?? ''),
                'memory_usage' => memory_get_peak_usage(true),
            ];

            AdminLog::logError(
                $eventType,
                $message,
                $context,
                $statusCode
            );

        } catch (Throwable $e) {
            // Si falló el logging, no rompemos la aplicación
            // Solo loggeamos el error en el log estándar de Laravel
            \Log::error('Failed to log error response', [
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'url' => $request->fullUrl(),
            ]);
        }
    }

    /**
     * Loggear excepción crítica
     */
    private function logException(Request $request, Throwable $exception, float $startTime): void
    {
        $duration = microtime(true) - $startTime;

        try {
            $eventType = $this->getEventTypeFromException($exception);
            $level = $this->isCriticalException($exception) ? 'critical' : 'error';

            // Capturar toda la información detallada del error aquí
            $context = [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'exception_code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'duration_ms' => round($duration * 1000, 2),
                'request_data' => $this->getSafeRequestData($request),
                'stack_trace' => $this->getDetailedStackTrace($exception),
                'memory_usage' => memory_get_peak_usage(true),
                'previous_exception' => $exception->getPrevious() ? [
                    'class' => get_class($exception->getPrevious()),
                    'message' => $exception->getPrevious()->getMessage(),
                    'file' => $exception->getPrevious()->getFile(),
                    'line' => $exception->getPrevious()->getLine(),
                ] : null,
                'error_context' => [
                    'php_version' => PHP_VERSION,
                    'timestamp' => now()->toISOString(),
                    'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ],
            ];

            if ($level === 'critical') {
                AdminLog::logCritical($eventType, $exception->getMessage(), $context, $exception);
            } else {
                AdminLog::logError($eventType, $exception->getMessage(), $context);
            }

            // Mark request as logged by middleware to prevent duplicate logging
            $request->attributes->set('logged_by_middleware', true);

        } catch (Throwable $e) {
            // Si falló el logging, no rompemos la aplicación
            \Log::error('Failed to log exception', [
                'error' => $e->getMessage(),
                'original_exception' => $exception->getMessage(),
                'url' => $request->fullUrl(),
            ]);
        }
    }

    /**
     * Obtener tipo de evento según el código de estado
     */
    private function getEventTypeFromStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            401 => AdminLog::EVENT_AUTHENTICATION_ERROR,
            403 => AdminLog::EVENT_SECURITY_VIOLATION,
            429 => 'rate_limit_exceeded',
            500 => AdminLog::EVENT_SYSTEM_ERROR,
            502, 503, 504 => AdminLog::EVENT_SYSTEM_ERROR,
            default => AdminLog::EVENT_API_ERROR
        };
    }

    /**
     * Obtener mensaje según el código de estado
     */
    private function getMessageFromStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'Unauthorized access attempt',
            403 => 'Forbidden access attempt',
            429 => 'Rate limit exceeded',
            500 => 'Internal server error',
            502 => 'Bad gateway error',
            503 => 'Service unavailable',
            504 => 'Gateway timeout',
            default => "HTTP error {$statusCode}"
        };
    }

    /**
     * Obtener tipo de evento según la excepción
     */
    private function getEventTypeFromException(Throwable $exception): string
    {
        $class = get_class($exception);

        return match (true) {
            str_contains($class, 'Database') => AdminLog::EVENT_DATABASE_ERROR,
            str_contains($class, 'Auth') => AdminLog::EVENT_AUTHENTICATION_ERROR,
            str_contains($class, 'Validation') => AdminLog::EVENT_VALIDATION_ERROR,
            str_contains($class, 'Payment') => AdminLog::EVENT_PAYMENT_ERROR,
            str_contains($class, 'Security') => AdminLog::EVENT_SECURITY_VIOLATION,
            default => AdminLog::EVENT_SYSTEM_ERROR
        };
    }

    /**
     * Determinar si una excepción es crítica
     */
    private function isCriticalException(Throwable $exception): bool
    {
        $class = get_class($exception);

        // Excepciones que consideramos críticas
        $criticalExceptions = [
            'OutOfMemoryError',
            'FatalError',
            'DatabaseException',
            'PDOException',
            'RedisException',
            'PaymentException',
        ];

        foreach ($criticalExceptions as $criticalClass) {
            if (str_contains($class, $criticalClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener datos seguros del request (sin información sensible)
     */
    private function getSafeRequestData(Request $request): array
    {
        $data = $request->all();

        // Remover información sensible
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'cvv',
            'card_number',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[HIDDEN]';
            }
        }

        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'params' => array_slice($data, 0, 10), // Solo los primeros 10 params
            'headers' => [
                'user_agent' => $request->userAgent(),
                'accept' => $request->header('Accept'),
                'content_type' => $request->header('Content-Type'),
            ],
            'ip' => $request->ip(),
        ];
    }

    /**
     * Obtener stack trace detallado para debugging
     */
    private function getDetailedStackTrace(Throwable $exception): array
    {
        $trace = $exception->getTrace();

        // Mantener las primeras 8 líneas del stack trace para mejor debugging
        $detailedTrace = array_slice($trace, 0, 8);

        // Mantener información completa para debugging pero limpiar algunos datos
        return array_map(function ($item) {
            return [
                'file' => isset($item['file']) ? $item['file'] : '[internal function]', // Keep full path for debugging
                'line' => $item['line'] ?? null,
                'function' => $item['function'] ?? null,
                'class' => $item['class'] ?? null,
                'type' => $item['type'] ?? null,
                // Keep args but limit size and clean sensitive data
                'args' => isset($item['args']) ? $this->cleanTraceArgs($item['args']) : [],
            ];
        }, $detailedTrace);
    }

    /**
     * Limpiar argumentos del stack trace (remover data sensible)
     */
    private function cleanTraceArgs(array $args): array
    {
        $cleaned = [];

        foreach (array_slice($args, 0, 3) as $index => $arg) { // Only first 3 args
            if (is_string($arg)) {
                // Check if it might be sensitive data
                if (strlen($arg) > 100) {
                    $cleaned[$index] = '[LARGE_STRING:'.strlen($arg).'_chars]';
                } elseif (preg_match('/password|token|secret|key/i', $arg)) {
                    $cleaned[$index] = '[SENSITIVE_DATA]';
                } else {
                    $cleaned[$index] = $arg;
                }
            } elseif (is_array($arg)) {
                $cleaned[$index] = '[ARRAY:'.count($arg).'_items]';
            } elseif (is_object($arg)) {
                $cleaned[$index] = '[OBJECT:'.get_class($arg).']';
            } else {
                $cleaned[$index] = $arg;
            }
        }

        return $cleaned;
    }
}
