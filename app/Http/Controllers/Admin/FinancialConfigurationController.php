<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * ⚠️ CONTROLADOR CRÍTICO - CONFIGURACIONES FINANCIERAS
 * 
 * Maneja configuraciones que afectan directamente los ingresos:
 * - Comisiones de plataforma
 * - Distribución de costos de envío
 * 
 * REQUIERE MÁXIMA SEGURIDAD Y AUDITORÍA
 */
class FinancialConfigurationController extends Controller
{
    /**
     * Claves de configuración financiera permitidas
     */
    private const FINANCIAL_KEYS = [
        'platform.commission_rate',
        'shipping.seller_percentage', 
        'shipping.max_seller_percentage'
    ];

    /**
     * Obtener todas las configuraciones financieras
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Verificar permisos de super admin
            if (!$this->isSuperAdmin()) {
                $this->logSecurityEvent('UNAUTHORIZED_FINANCIAL_ACCESS', $request);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Acceso denegado: se requieren permisos de super administrador'
                ], 403);
            }

            // Obtener configuraciones financieras
            $configurations = Configuration::whereIn('key', self::FINANCIAL_KEYS)
                ->select('key', 'value', 'description', 'updated_at')
                ->get()
                ->keyBy('key');

            // Formatear respuesta con valores por defecto
            $response = [
                'platform_commission_rate' => $configurations->get('platform.commission_rate')?->value ?? '10.0',
                'shipping_seller_percentage' => $configurations->get('shipping.seller_percentage')?->value ?? '80.0', 
                'shipping_max_seller_percentage' => $configurations->get('shipping.max_seller_percentage')?->value ?? '40.0',
                'last_updated' => $configurations->max('updated_at'),
            ];

            Log::info('Financial configurations accessed', [
                'admin_id' => Auth::id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error retrieving financial configurations', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno al obtener configuraciones'
            ], 500);
        }
    }

    /**
     * Actualizar configuraciones financieras
     */
    public function update(Request $request): JsonResponse
    {
        try {
            // Verificar permisos de super admin
            if (!$this->isSuperAdmin()) {
                $this->logSecurityEvent('UNAUTHORIZED_FINANCIAL_MODIFICATION', $request);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Acceso denegado: se requieren permisos de super administrador'
                ], 403);
            }

            // Validar entrada
            $validator = $this->validateFinancialInput($request);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Validaciones de negocio adicionales
            $businessValidation = $this->validateBusinessRules($validated);
            if ($businessValidation !== null) {
                return $businessValidation;
            }

            // Obtener valores actuales para auditoría
            $oldValues = $this->getCurrentValues();

            // Actualizar configuraciones en transacción
            DB::transaction(function () use ($validated) {
                $this->updateConfiguration('platform.commission_rate', $validated['platform_commission_rate']);
                $this->updateConfiguration('shipping.seller_percentage', $validated['shipping_seller_percentage']);
                $this->updateConfiguration('shipping.max_seller_percentage', $validated['shipping_max_seller_percentage']);
            });

            // Log de auditoría completo
            $this->logConfigurationChange($oldValues, $validated, $request);

            return response()->json([
                'success' => true,
                'message' => 'Configuraciones financieras actualizadas exitosamente',
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating financial configurations', [
                'error' => $e->getMessage(),
                'admin_id' => Auth::id(),
                'input' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno al actualizar configuraciones'
            ], 500);
        }
    }

    /**
     * Validar datos de entrada
     */
    private function validateFinancialInput(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), [
            'platform_commission_rate' => [
                'required',
                'numeric',
                'min:0',
                'max:50',
                'regex:/^\d+(\.\d{1,2})?$/' // Máximo 2 decimales
            ],
            'shipping_seller_percentage' => [
                'required',
                'numeric', 
                'min:0',
                'max:100',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],
            'shipping_max_seller_percentage' => [
                'required',
                'numeric',
                'min:0', 
                'max:100',
                'regex:/^\d+(\.\d{1,2})?$/'
            ]
        ], [
            'platform_commission_rate.required' => 'La comisión de plataforma es obligatoria',
            'platform_commission_rate.numeric' => 'La comisión debe ser un número',
            'platform_commission_rate.min' => 'La comisión no puede ser negativa',
            'platform_commission_rate.max' => 'La comisión no puede ser mayor al 50%',
            'platform_commission_rate.regex' => 'La comisión debe tener máximo 2 decimales',
            
            'shipping_seller_percentage.required' => 'El porcentaje de envío es obligatorio',
            'shipping_seller_percentage.numeric' => 'El porcentaje debe ser un número',
            'shipping_seller_percentage.min' => 'El porcentaje no puede ser negativo',
            'shipping_seller_percentage.max' => 'El porcentaje no puede ser mayor al 100%',
            'shipping_seller_percentage.regex' => 'El porcentaje debe tener máximo 2 decimales',
            
            'shipping_max_seller_percentage.required' => 'El porcentaje máximo es obligatorio',
            'shipping_max_seller_percentage.numeric' => 'El porcentaje debe ser un número',
            'shipping_max_seller_percentage.min' => 'El porcentaje no puede ser negativo',
            'shipping_max_seller_percentage.max' => 'El porcentaje no puede ser mayor al 100%',
            'shipping_max_seller_percentage.regex' => 'El porcentaje debe tener máximo 2 decimales',
        ]);
    }

    /**
     * Validar reglas de negocio
     */
    private function validateBusinessRules(array $validated): ?JsonResponse
    {
        $sellerPercentage = (float) $validated['shipping_seller_percentage'];
        $maxSellerPercentage = (float) $validated['shipping_max_seller_percentage'];

        // El porcentaje para un solo vendedor debe ser mayor al máximo para múltiples
        if ($maxSellerPercentage >= $sellerPercentage) {
            return response()->json([
                'status' => 'error',
                'message' => 'Regla de negocio violada',
                'errors' => [
                    'shipping_max_seller_percentage' => [
                        'El porcentaje máximo debe ser menor al porcentaje para un solo vendedor'
                    ]
                ]
            ], 422);
        }

        // Validar que la comisión sea razonable
        $commission = (float) $validated['platform_commission_rate'];
        if ($commission > 25) {
            return response()->json([
                'status' => 'error',
                'message' => 'Advertencia de negocio',
                'errors' => [
                    'platform_commission_rate' => [
                        'Una comisión mayor al 25% podría afectar negativamente a los vendedores'
                    ]
                ]
            ], 422);
        }

        return null;
    }

    /**
     * Actualizar una configuración específica
     */
    private function updateConfiguration(string $key, $value): void
    {
        Configuration::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'group' => 'financial',
                'type' => 'decimal',
                'updated_at' => now()
            ]
        );
    }

    /**
     * Obtener valores actuales para auditoría
     */
    private function getCurrentValues(): array
    {
        $configurations = Configuration::whereIn('key', self::FINANCIAL_KEYS)
            ->get()
            ->keyBy('key');

        return [
            'platform_commission_rate' => $configurations->get('platform.commission_rate')?->value ?? '10.0',
            'shipping_seller_percentage' => $configurations->get('shipping.seller_percentage')?->value ?? '80.0',
            'shipping_max_seller_percentage' => $configurations->get('shipping.max_seller_percentage')?->value ?? '40.0',
        ];
    }

    /**
     * Verificar si el usuario es super admin
     */
    private function isSuperAdmin(): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        return $user->isAdmin() && 
               $user->admin && 
               $user->admin->role === 'super_admin';
    }

    /**
     * Log de eventos de seguridad
     */
    private function logSecurityEvent(string $event, Request $request): void
    {
        Log::warning('SECURITY EVENT: ' . $event, [
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
            'requested_url' => $request->fullUrl()
        ]);
    }

    /**
     * Log de auditoría para cambios de configuración
     */
    private function logConfigurationChange(array $oldValues, array $newValues, Request $request): void
    {
        $changes = [];
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue !== (string) $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => (string) $newValue
                ];
            }
        }

        Log::info('FINANCIAL CONFIGURATION CHANGED', [
            'admin_id' => Auth::id(),
            'admin_email' => Auth::user()->email,
            'changes' => $changes,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()
        ]);

        // También guardar en tabla de auditoría si existe
        try {
            DB::table('admin_logs')->insert([
                'admin_id' => Auth::id(),
                'action' => 'financial_configuration_update',
                'details' => json_encode([
                    'changes' => $changes,
                    'ip' => $request->ip()
                ]),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            // Si no existe la tabla, solo loggeamos
            Log::warning('Could not save to admin_logs table: ' . $e->getMessage());
        }
    }
}