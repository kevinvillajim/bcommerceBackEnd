<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DeunaWebhookMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip validation in local environment for testing
        if (config('app.env') === 'local' && $request->has('skip_validation')) {
            Log::info('Skipping webhook validation in local environment');

            return $next($request);
        }

        try {
            // Validate content type
            if (! $request->isJson()) {
                Log::warning('Invalid content type for webhook', [
                    'content_type' => $request->header('Content-Type'),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid content type. Expected application/json',
                ], 400);
            }

            // Check for signature header (optional but recommended)
            $signature = $request->header('X-DeUna-Signature')
                ?? $request->header('x-deuna-signature')
                ?? $request->header('signature');

            // Validate JSON payload
            $rawBody = $request->getContent();
            if (empty($rawBody)) {
                Log::warning('Empty webhook payload received', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Empty payload',
                ], 400);
            }

            $decodedBody = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Invalid JSON in webhook payload', [
                    'json_error' => json_last_error_msg(),
                    'ip' => $request->ip(),
                    'payload_preview' => substr($rawBody, 0, 100).'...',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON payload',
                ], 400);
            }

            // Validate required fields based on DeUna webhook structure
            $requiredFields = ['idTransacionReference']; // DeUna uses this field
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (! isset($decodedBody[$field]) && ! isset($decodedBody['data'][$field])) {
                    $missingFields[] = $field;
                }
            }

            if (! empty($missingFields)) {
                Log::warning('Missing required fields in webhook payload', [
                    'missing_fields' => $missingFields,
                    'received_fields' => array_keys($decodedBody),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields: '.implode(', ', $missingFields),
                ], 400);
            }

            // Log successful validation
            Log::info('Webhook validation passed', [
                'has_signature' => ! empty($signature),
                'payload_size' => strlen($rawBody),
                'ip' => $request->ip(),
                'event' => $decodedBody['event'] ?? $decodedBody['eventType'] ?? 'unknown',
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Error in webhook middleware', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook validation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
