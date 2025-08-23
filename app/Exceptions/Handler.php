<?php

namespace App\Exceptions;

use App\Models\AdminLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * La lista de inputs que nunca se mostrarán en mensajes de error
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Registrar las funciones de manejo de excepciones
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Solo loggear excepciones críticas que no se capturan en middleware
            if ($this->shouldReportToAdminLog($e)) {
                $this->logCriticalException($e);
            }
        });
    }

    /**
     * Determinar si debemos reportar esta excepción al AdminLog
     */
    private function shouldReportToAdminLog(Throwable $exception): bool
    {
        // ALWAYS report for test routes
        if (request() && request()->is('api/test-*')) {
            return true;
        }

        // No reportar si ya se reportó en el middleware
        if (request() && request()->attributes->get('logged_by_middleware')) {
            return false;
        }

        $class = get_class($exception);

        // Lista de excepciones críticas que siempre queremos loggear
        $criticalExceptions = [
            'Exception', // Include generic Exception
            'OutOfMemoryError',
            'FatalError',
            'ErrorException',
            'Illuminate\Database\QueryException',
            'PDOException',
            'Illuminate\Queue\MaxAttemptsExceededException',
            'Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException',
        ];

        foreach ($criticalExceptions as $criticalClass) {
            if (str_contains($class, $criticalClass) || $exception instanceof $criticalClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Loggear excepción crítica al AdminLog
     */
    private function logCriticalException(Throwable $exception): void
    {
        try {
            $eventType = $this->getEventTypeFromException($exception);

            $context = [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'stack_trace' => $this->getCleanTrace($exception),
                'exception_message' => $exception->getMessage(),
                'exception_code' => $exception->getCode(),
                'memory_usage' => memory_get_peak_usage(true),
                'previous_exception' => $exception->getPrevious() ? [
                    'class' => get_class($exception->getPrevious()),
                    'message' => $exception->getPrevious()->getMessage(),
                    'file' => $exception->getPrevious()->getFile(),
                    'line' => $exception->getPrevious()->getLine(),
                ] : null,
                'request_info' => request() ? [
                    'method' => request()->method(),
                    'url' => request()->fullUrl(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ] : null,
            ];

            AdminLog::logCritical(
                $eventType,
                $exception->getMessage(),
                $context,
                $exception
            );

        } catch (Throwable $e) {
            // Si falla el logging, usar el log estándar de Laravel
            \Log::error('Failed to log critical exception to AdminLog', [
                'error' => $e->getMessage(),
                'original_exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Obtener tipo de evento basado en la excepción
     */
    private function getEventTypeFromException(Throwable $exception): string
    {
        $class = get_class($exception);

        return match (true) {
            str_contains($class, 'Database') || str_contains($class, 'PDO') => AdminLog::EVENT_DATABASE_ERROR,
            str_contains($class, 'Auth') => AdminLog::EVENT_AUTHENTICATION_ERROR,
            str_contains($class, 'Validation') => AdminLog::EVENT_VALIDATION_ERROR,
            str_contains($class, 'Payment') => AdminLog::EVENT_PAYMENT_ERROR,
            str_contains($class, 'Security') => AdminLog::EVENT_SECURITY_VIOLATION,
            str_contains($class, 'Queue') => 'queue_error',
            str_contains($class, 'Memory') => 'memory_error',
            default => AdminLog::EVENT_SYSTEM_ERROR
        };
    }

    /**
     * Obtener trace limpio de la excepción
     */
    private function getCleanTrace(Throwable $exception): array
    {
        $trace = $exception->getTrace();

        // Get first 8 lines for better debugging info
        $cleanTrace = array_slice($trace, 0, 8);

        return array_map(function ($item) {
            return [
                'file' => isset($item['file']) ? $item['file'] : null, // Keep full path for debugging
                'line' => $item['line'] ?? null,
                'function' => $item['function'] ?? null,
                'class' => $item['class'] ?? null,
                'type' => $item['type'] ?? null,
            ];
        }, $cleanTrace);
    }

    /**
     * Convertir una excepción de autenticación en una respuesta HTTP
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // Para solicitudes API o cualquier solicitud que espere JSON, devolver una respuesta JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Para solicitudes web, usar una ruta que existe - si no hay 'login', redirecciona a '/'
        return redirect()->guest(route('login') ?? '/');
    }
}
